<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Container\Container;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Core\Response;
use Celema\Quma\Database;
use Cosray\Cms;
use Cosray\Config;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Menus as MenuWriter;
use Cosray\Middleware\Permission;

/**
 * The menus area: the listing with menu properties, and per menu the
 * item tree with its editing side pane. Every pane interaction is a
 * URL — selection is `?item=`, creation `?add=` — so the whole screen
 * re-renders as one `main` swap and stays deep-linkable.
 */
final class Menus extends Panel
{
	protected const string AREA = 'menus';

	private const string HANDLE_PATTERN = '/^[a-z0-9-]{1,32}$/';

	private const string TYPE_PATTERN = '/^[a-z][a-z0-9-]{0,31}$/';

	/** Handles that would shadow a literal route segment. */
	private const array RESERVED_HANDLES = ['create'];

	public function __construct(
		Config $config,
		Container $container,
		Request $request,
		private readonly Database $db,
		private readonly MenuWriter $menus,
	) {
		parent::__construct($config, $container, $request);
	}

	#[Permission('edit-menus')]
	public function index(): array
	{
		$menus = [];

		foreach ($this->db->menus->list()->all() as $row) {
			$menu = (string) $row['menu'];

			$menus[] = [
				'menu' => $menu,
				'description' => (string) $row['description'],
				'items' => (int) $row['items'],
				'treeUrl' => $this->url($menu),
				'editUrl' => $this->url($menu, '/edit'),
				'deleteUrl' => $this->url($menu, '/delete'),
			];
		}

		return $this->context([
			'menus' => $menus,
			'createUrl' => $this->base() . '/create',
			'notice' => $this->notice(),
		]);
	}

	#[Permission('edit-menus')]
	public function create(): array
	{
		return $this->form('', '', null, []);
	}

	#[Permission('edit-menus')]
	public function store(Factory $factory): array|Response
	{
		[$handle, $description] = $this->submitted();
		$errors = $this->validate($handle, $description, null);

		if ($errors !== []) {
			return $this->form($handle, $description, null, $errors);
		}

		$this->menus->create($handle, $description);

		return $this->redirect($factory, 'created');
	}

	#[Permission('edit-menus')]
	public function edit(string $menu): array
	{
		$row = $this->row($menu);

		return $this->form($menu, (string) $row['description'], $menu, []);
	}

	#[Permission('edit-menus')]
	public function update(Factory $factory, string $menu): array|Response
	{
		$this->row($menu);
		[$handle, $description] = $this->submitted();
		$errors = $this->validate($handle, $description, $menu);

		if ($errors !== []) {
			return $this->form($handle, $description, $menu, $errors);
		}

		$this->menus->update($menu, $description);

		if ($handle !== $menu) {
			$this->menus->rename($menu, $handle);
		}

		return $this->redirect($factory, 'updated');
	}

	#[Permission('edit-menus')]
	public function delete(Factory $factory, string $menu): Response
	{
		$this->row($menu);
		$this->menus->delete($menu);

		return $this->redirect($factory, 'deleted');
	}

	#[Permission('edit-menus')]
	public function menu(Cms $cms, Context $context, string $menu): array
	{
		return $this->treeContext($cms, $context, $menu, $this->pane($cms, $context, $menu));
	}

	#[Permission('edit-menus')]
	public function storeItem(
		Cms $cms,
		Context $context,
		Factory $factory,
		string $menu,
	): array|Response {
		$this->row($menu);
		$body = $this->formData();
		$parent = trim((string) ($body['parent'] ?? ''));
		$parent = $parent === '' ? null : $parent;

		if ($parent !== null) {
			$this->itemRowFor($menu, $parent);
		}

		$values = $this->valuesFromBody($context, $body);
		[$data, $errors] = $this->itemPayload($cms, $context, $values);

		if ($errors !== []) {
			return $this->treeContext($cms, $context, $menu, $this->paneContext(
				$cms,
				$context,
				$menu,
				'create',
				null,
				$parent,
				$values,
				$errors,
			));
		}

		$item = $this->menus->add($menu, $data, $parent);

		return $this->redirectToMenu($factory, $menu, [
			'item' => $item,
			'notice' => 'item-created',
		]);
	}

	#[Permission('edit-menus')]
	public function updateItem(
		Cms $cms,
		Context $context,
		Factory $factory,
		string $menu,
		string $item,
	): array|Response {
		$this->itemRowFor($menu, $item);
		$values = $this->valuesFromBody($context, $this->formData());
		[$data, $errors] = $this->itemPayload($cms, $context, $values);

		if ($errors !== []) {
			return $this->treeContext($cms, $context, $menu, $this->paneContext(
				$cms,
				$context,
				$menu,
				'edit',
				$item,
				null,
				$values,
				$errors,
			));
		}

		$this->menus->updateItem($item, $data);

		return $this->redirectToMenu($factory, $menu, [
			'item' => $item,
			'notice' => 'item-saved',
		]);
	}

	#[Permission('edit-menus')]
	public function moveItem(Factory $factory, string $menu, string $item): Response
	{
		$row = $this->itemRowFor($menu, $item);
		$body = $this->formData();

		try {
			if (isset($body['index'])) {
				// The drag contract: an explicit target group and index.
				$parent = trim((string) ($body['parent'] ?? ''));
				$this->menus->place(
					$item,
					$parent === '' ? null : $parent,
					max(0, (int) $body['index']),
				);
			} else {
				$parent = $row['parent'] === null ? null : (string) $row['parent'];
				$siblings = array_column(
					$this->db->menus->siblings(['menu' => $menu, 'parent' => $parent])->all(),
					'item',
				);
				$index = (int) array_search($item, $siblings, true);
				$offset = ($body['direction'] ?? '') === 'up' ? -1 : 1;
				$this->menus->place($item, $parent, max(0, $index + $offset));
			}
		} catch (RuntimeException) {
			return $this->redirectToMenu($factory, $menu, [
				'item' => $item,
				'notice' => 'move-rejected',
			]);
		}

		return $this->redirectToMenu($factory, $menu, ['item' => $item]);
	}

	#[Permission('edit-menus')]
	public function deleteItem(Factory $factory, string $menu, string $item): Response
	{
		$this->itemRowFor($menu, $item);
		$this->menus->remove($item);

		return $this->redirectToMenu($factory, $menu, ['notice' => 'item-deleted']);
	}

	private function treeContext(Cms $cms, Context $context, string $menu, ?array $pane): array
	{
		$row = $this->row($menu);

		return $this->context([
			'menu' => $menu,
			'description' => (string) $row['description'],
			'itemCount' => (int) $row['items'],
			'tree' => $this->branch($cms->menu($menu)),
			'pane' => $pane,
			'notice' => $this->treeNotice(),
			'urls' => [
				'menus' => $this->base(),
				'tree' => $this->url($menu),
				'edit' => $this->url($menu, '/edit'),
				'add' => $this->url($menu) . '?add=',
			],
		]);
	}

	/**
	 * The pane state the URL asks for: `?item=` edits, `?add=` creates
	 * (empty at the root, a uid below that item), otherwise no pane form.
	 */
	private function pane(Cms $cms, Context $context, string $menu): ?array
	{
		$item = $this->request->param('item', null);

		if (is_string($item) && $item !== '') {
			$data = json_decode((string) $this->itemRowFor($menu, $item)['data'], true);

			return $this->paneContext(
				$cms,
				$context,
				$menu,
				'edit',
				$item,
				null,
				$this->valuesFromData(is_array($data) ? $data : []),
				[],
			);
		}

		$add = $this->request->param('add', null);

		if (is_string($add)) {
			$parent = trim($add);

			if ($parent !== '') {
				$this->itemRowFor($menu, $parent);
			}

			return $this->paneContext(
				$cms,
				$context,
				$menu,
				'create',
				null,
				$parent === '' ? null : $parent,
				$this->valuesFromData([]),
				[],
			);
		}

		return null;
	}

	/** @param array<string, string> $errors */
	private function paneContext(
		Cms $cms,
		Context $context,
		string $menu,
		string $mode,
		?string $item,
		?string $parent,
		array $values,
		array $errors,
	): array {
		$values['nodeLabel'] = $values['node'] === ''
			? ''
			: $cms->node->byUid($values['node'], published: null)?->label() ?? $values['node'];
		$values['assetLabel'] = $this->assetLabel($context, $values['asset']);
		$values['imageLabel'] = $this->assetLabel($context, $values['image']);

		return [
			'mode' => $mode,
			'item' => $item,
			'action' => $mode === 'create'
				? $this->url($menu, '/item/create')
				: $this->url($menu, '/item/' . rawurlencode((string) $item)),
			'parent' => $parent,
			'parentTitle' => $parent === null ? null : $this->itemTitle($menu, $parent),
			'values' => $values,
			'errors' => $errors,
			'cancelUrl' => $this->url($menu),
			'locales' => $this->localeList($context),
			'defaultLocale' => $context->locales()->getDefault()->id,
			'searchUrls' => [
				'node' => $this->panelPath() . '/reference/nodes?limit=8',
				'asset' => '/media/library?kind=',
				'image' => '/media/library?kind=image',
			],
		];
	}

	/**
	 * Flattens one sibling group of the menu iterator into view rows,
	 * recursing into children.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function branch(iterable $items): array
	{
		$rows = [];

		foreach ($items as $entry) {
			$children = $this->branch($entry->children());
			$rows[] = [
				'id' => $entry->id(),
				'type' => $entry->type(),
				'title' => $entry->title(),
				'href' => $entry->href(),
				'children' => $children,
				'descendants' =>
					count($children)
						+ (int) array_sum(array_column($children, 'descendants')),
				'first' => false,
				'last' => false,
			];
		}

		if ($rows !== []) {
			$rows[0]['first'] = true;
			$rows[array_key_last($rows)]['last'] = true;
		}

		return $rows;
	}

	/**
	 * Builds the jsonb payload from normalized form values; validation
	 * errors keep the offending key out of the payload.
	 *
	 * @return array{0: array<string, mixed>, 1: array<string, string>}
	 */
	private function itemPayload(Cms $cms, Context $context, array $values): array
	{
		$errors = [];
		$type = $values['type'];

		if (preg_match(self::TYPE_PATTERN, $type) !== 1) {
			return [[], ['type' => __('menu:error-item-type')]];
		}

		$data = ['type' => $type];

		if ($values['title'] !== []) {
			$data['title'] = $values['title'];
		} elseif ($type !== 'node') {
			$errors['title'] = __('menu:error-item-title');
		}

		if ($type === 'node') {
			if (
				$values['node'] === ''
				|| $cms->node->byUid($values['node'], published: null) === null
			) {
				$errors['node'] = __('menu:error-item-node');
			} else {
				$data['node'] = $values['node'];
			}
		}

		if ($type === 'url') {
			if ($values['path'] === []) {
				$errors['path'] = __('menu:error-item-path');
			} else {
				foreach ($values['path'] as $path) {
					if (preg_match('#^(/|https?://|mailto:|tel:)#', $path) !== 1) {
						$errors['path'] = __('menu:error-item-path-shape');

						break;
					}
				}

				if (!isset($errors['path'])) {
					$data['path'] = $values['path'];
				}
			}
		}

		if ($type === 'asset') {
			if ($values['asset'] === '' || $context->assets()->get($values['asset']) === null) {
				$errors['asset'] = __('menu:error-item-asset');
			} else {
				$data['asset'] = $values['asset'];
			}
		}

		if ($values['target'] && in_array($type, ['node', 'url', 'asset'], true)) {
			$data['target'] = '_blank';
		}

		if ($values['class'] !== '') {
			if (mb_strlen($values['class']) > 64) {
				$errors['class'] = __('menu:error-item-class');
			} else {
				$data['class'] = $values['class'];
			}
		}

		if ($values['image'] !== '') {
			if ($context->assets()->get($values['image']) === null) {
				$errors['image'] = __('menu:error-item-image');
			} else {
				$data['image'] = $values['image'];
			}
		}

		return [$data, $errors];
	}

	/** @return array<string, mixed> */
	private function valuesFromBody(Context $context, array $body): array
	{
		$type = trim((string) ($body['type'] ?? ''));

		return [
			'type' => $type === '' ? 'label' : $type,
			'title' => $this->localeMap($context, $body['title'] ?? null),
			'node' => trim((string) ($body['node'] ?? '')),
			'path' => $this->localeMap($context, $body['path'] ?? null),
			'asset' => trim((string) ($body['asset'] ?? '')),
			'target' => ($body['target'] ?? '') === '_blank',
			'class' => trim((string) ($body['class'] ?? '')),
			'image' => trim((string) ($body['image'] ?? '')),
		];
	}

	/** @return array<string, mixed> */
	private function valuesFromData(array $data): array
	{
		$map = static fn(mixed $value): array => (
			is_array($value)
				? array_filter($value, static fn($entry) => is_string($entry) && $entry !== '')
				: []
		);
		$type = $data['type'] ?? null;

		return [
			'type' => is_string($type) && $type !== '' ? $type : 'node',
			'title' => $map($data['title'] ?? null),
			'node' => is_string($data['node'] ?? null) ? $data['node'] : '',
			'path' => $map($data['path'] ?? null),
			'asset' => is_string($data['asset'] ?? null) ? $data['asset'] : '',
			'target' => ($data['target'] ?? '') === '_blank',
			'class' => is_string($data['class'] ?? null) ? $data['class'] : '',
			'image' => is_string($data['image'] ?? null) ? $data['image'] : '',
		];
	}

	/**
	 * Submitted per-locale values reduced to the configured locales,
	 * empties dropped.
	 *
	 * @return array<string, string>
	 */
	private function localeMap(Context $context, mixed $values): array
	{
		if (!is_array($values)) {
			return [];
		}

		$map = [];

		foreach ($context->locales() as $locale) {
			$value = $values[$locale->id] ?? null;

			if (is_string($value) && trim($value) !== '') {
				$map[$locale->id] = trim($value);
			}
		}

		return $map;
	}

	/** @return list<array{id: string, title: string}> */
	private function localeList(Context $context): array
	{
		return array_map(
			static fn($locale) => ['id' => $locale->id, 'title' => $locale->title],
			iterator_to_array($context->locales(), false),
		);
	}

	private function assetLabel(Context $context, string $uid): string
	{
		if ($uid === '') {
			return '';
		}

		return $context->assets()->get($uid)->filename ?? $uid;
	}

	/** Any stored title of the item, for display hints; the id as fallback. */
	private function itemTitle(string $menu, string $item): string
	{
		$data = json_decode((string) $this->itemRowFor($menu, $item)['data'], true);
		$titles = is_array($data['title'] ?? null)
			? array_filter($data['title'], 'is_string')
			: [];

		return $titles === [] ? $item : (string) reset($titles);
	}

	/** @param array<string, string> $errors */
	private function form(
		string $handle,
		string $description,
		?string $current,
		array $errors,
	): array {
		return $this->context([
			'mode' => $current === null ? 'create' : 'edit',
			'action' => $current === null
				? $this->base() . '/create'
				: $this->url($current, '/edit'),
			'backUrl' => $this->base(),
			'handle' => $handle,
			'description' => $description,
			'errors' => $errors,
		]);
	}

	/** @return array<string, string> */
	private function validate(string $handle, string $description, ?string $current): array
	{
		$errors = [];

		if (preg_match(self::HANDLE_PATTERN, $handle) !== 1) {
			$errors['menu'] = __('menu:error-handle');
		} elseif (in_array($handle, self::RESERVED_HANDLES, true)) {
			$errors['menu'] = __('menu:error-handle-reserved');
		} elseif ($handle !== $current && $this->db->menus->exists(['menu' => $handle])->first()) {
			$errors['menu'] = __('menu:error-handle-taken');
		}

		if (trim($description) === '' || mb_strlen($description) > 128) {
			$errors['description'] = __('menu:error-description');
		}

		return $errors;
	}

	/** @return array{0: string, 1: string} */
	private function submitted(): array
	{
		$data = $this->formData();
		$handle = $data['menu'] ?? null;
		$description = $data['description'] ?? null;

		return [
			is_string($handle) ? trim($handle) : '',
			is_string($description) ? trim($description) : '',
		];
	}

	private function notice(): ?string
	{
		// Literal ids so the i18n scanner sees every key.
		return match ($this->request->param('notice', '')) {
			'created' => __('menu:notice-created'),
			'updated' => __('menu:notice-updated'),
			'deleted' => __('menu:notice-deleted'),
			default => null,
		};
	}

	private function treeNotice(): ?string
	{
		return match ($this->request->param('notice', '')) {
			'item-created' => __('menu:notice-item-created'),
			'item-saved' => __('menu:notice-item-saved'),
			'item-deleted' => __('menu:notice-item-deleted'),
			'move-rejected' => __('menu:notice-move-rejected'),
			default => null,
		};
	}

	private function redirect(Factory $factory, string $notice): Response
	{
		return Response::create($factory)->redirect(
			$this->base() . '?notice=' . $notice,
			303,
		);
	}

	/** @param array<string, string> $params */
	private function redirectToMenu(Factory $factory, string $menu, array $params = []): Response
	{
		$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

		return Response::create($factory)->redirect(
			$this->url($menu) . ($query === '' ? '' : '?' . $query),
			303,
		);
	}

	private function row(string $menu): array
	{
		foreach ($this->db->menus->list()->all() as $row) {
			if ($row['menu'] === $menu) {
				return $row;
			}
		}

		throw new HttpNotFound($this->request);
	}

	/** The item's row, 404 unless it belongs to this menu. */
	private function itemRowFor(string $menu, string $item): array
	{
		$row = $this->db->menus->itemRow(['item' => $item])->first();

		if (!$row || $row['menu'] !== $menu) {
			throw new HttpNotFound($this->request);
		}

		return $row;
	}

	private function base(): string
	{
		return $this->panelPath() . '/menus';
	}

	private function url(string $menu, string $suffix = ''): string
	{
		return $this->base() . '/' . rawurlencode($menu) . $suffix;
	}
}
