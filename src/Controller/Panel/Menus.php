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
use Cosray\Finder\Menu as FinderMenu;
use Cosray\Menus as MenuWriter;
use Cosray\Middleware\Permission;
use Cosray\User;

/**
 * The menus area: the rail lists the menus, and per menu the item tree
 * with its editing side pane. Every pane interaction is a URL —
 * selection is `?item=`, creation `?add=` — so the whole screen
 * re-renders as one `main` swap and stays deep-linkable.
 */
final class Menus extends Panel
{
	protected const string AREA = 'menus';

	private const string HANDLE_PATTERN = '/^[a-z0-9-]{1,32}$/';

	private const string TYPE_PATTERN = '/^[a-z][a-z0-9-]{0,31}$/';

	/** Handles that would shadow a literal route segment. */
	private const array RESERVED_HANDLES = ['create'];

	/** @var ?list<array{menu: string, description: string, items: int, url: string}> */
	private ?array $menuRows = null;

	public function __construct(
		Config $config,
		Container $container,
		Request $request,
		private readonly Database $db,
		private readonly MenuWriter $menus,
	) {
		parent::__construct($config, $container, $request);
	}

	/**
	 * Every screen in the area renders the rail, so its menus ride the
	 * shared context. The rail replaces a listing screen: `rail` is what
	 * the base class leaves off outside the content area.
	 */
	protected function context(array $data = []): array
	{
		$menus = $this->menuRows();

		return parent::context(array_merge([
			'menuNav' => $menus,
			'menuCreateUrl' => $this->base() . '/create',
			'manages' => $this->manages(),
			'rail' => $menus !== [],
		], $data));
	}

	/**
	 * Whether the user may add, rename, or remove menus. A menu's handle is
	 * what templates fetch it by, so changing the set of menus edits the
	 * site's markup contract, not its content.
	 */
	private function manages(): bool
	{
		$user = $this->request->get('user', null);

		return $user instanceof User && $user->hasPermission('manage-menus');
	}

	/**
	 * The area's entry point. With menus around, the rail is the listing
	 * and this opens the first one; only an empty project stops here.
	 */
	#[Permission('edit-menus')]
	public function index(Factory $factory): array|Response
	{
		$menus = $this->menuRows();

		if ($menus !== []) {
			$notice = $this->request->param('notice', '');

			return $this->redirectToMenu(
				$factory,
				$menus[0]['menu'],
				is_string($notice) && $notice !== '' ? ['notice' => $notice] : [],
			);
		}

		return $this->context(['notice' => $this->notice()]);
	}

	#[Permission('manage-menus')]
	public function create(): array
	{
		return $this->form('', '', []);
	}

	#[Permission('manage-menus')]
	public function store(Factory $factory): array|Response
	{
		[$handle, $description] = $this->submitted();
		$errors = $this->validate($handle, $description, null);

		if ($errors !== []) {
			return $this->form($handle, $description, $errors);
		}

		$this->menus->create($handle, $description);

		return $this->redirectToMenu($factory, $handle, ['notice' => 'created']);
	}

	#[Permission('edit-menus')]
	public function update(
		Cms $cms,
		Context $context,
		Factory $factory,
		string $menu,
	): array|Response {
		$this->row($menu);
		[$handle, $description] = $this->submitted();

		// The field is disabled without the permission, so nothing legitimate
		// posts a handle here; ignore whatever does.
		if (!$this->manages()) {
			$handle = $menu;
		}

		$errors = $this->validate($handle, $description, $menu);

		if ($errors !== []) {
			return $this->treeContext($cms, $context, $menu, null, [
				'handle' => $handle,
				'description' => $description,
				'errors' => $errors,
			]);
		}

		$this->menus->update($menu, $description);

		if ($handle !== $menu) {
			$this->menus->rename($menu, $handle);
		}

		return $this->redirectToMenu($factory, $handle, ['notice' => 'updated']);
	}

	#[Permission('manage-menus')]
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

	/**
	 * @param ?array{handle: string, description: string, errors: array<string, string>} $props
	 *   the submitted menu fields when a save came back with errors, the
	 *   stored ones otherwise
	 */
	private function treeContext(
		Cms $cms,
		Context $context,
		string $menu,
		?array $pane,
		?array $props = null,
	): array {
		$row = $this->row($menu);

		return $this->context([
			'menu' => $menu,
			'description' => (string) $row['description'],
			'itemCount' => (int) $row['items'],
			'props' => [
				...(
					$props ?? [
						'handle' => $menu,
						'description' => (string) $row['description'],
						'errors' => [],
					]
				),
				'confirm' => $this->deleteConfirm($menu),
			],
			// Unexpanded: the editor shows `children` items as stored,
			// not what they resolve into. The preview beneath renders the
			// expanded menu as the frontend would emit it.
			'tree' => $this->branch(new FinderMenu($context, $menu, expand: false), $cms),
			'preview' => $cms->menu($menu)->html(),
			'pane' => $pane,
			'notice' => $this->notice(),
			'urls' => [
				'tree' => $this->url($menu),
				'edit' => $this->url($menu, '/edit'),
				'delete' => $this->url($menu, '/delete'),
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
	private function branch(iterable $items, Cms $cms): array
	{
		$rows = [];

		foreach ($items as $entry) {
			$children = $this->branch($entry->children(), $cms);
			$title = $entry->title();

			if ($entry->type() === 'children') {
				$node = $entry->node();
				$title = __('menu:children-of', [
					'title' => $node === null
						? ''
						: $cms->node->byUid($node, published: null)?->label() ?? $node,
				]);
			}

			$rows[] = [
				'id' => $entry->id(),
				'type' => $entry->type(),
				'title' => $title,
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

		// A `children` item is pure configuration: the linked node, the
		// depth, and the order — no label of its own.
		if ($type === 'children') {
			if (
				$values['node'] === ''
				|| $cms->node->byUid($values['node'], published: null) === null
			) {
				$errors['node'] = __('menu:error-item-node');
			} else {
				$data['node'] = $values['node'];
			}

			$data['levels'] = min(5, max(1, (int) $values['levels']));
			$data['order'] = in_array($values['order'], FinderMenu::CHILD_ORDERS, true)
				? $values['order']
				: 'title';

			return [$data, $errors];
		}

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
			'levels' => (int) ($body['levels'] ?? 1),
			'order' => trim((string) ($body['order'] ?? '')),
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
			'levels' => max(1, (int) ($data['levels'] ?? 1)),
			'order' => is_string($data['order'] ?? null) ? $data['order'] : '',
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

	/**
	 * The create screen. Editing a menu happens inline on its tree screen,
	 * so this is the only standalone menu form left.
	 *
	 * @param array<string, string> $errors
	 */
	private function form(string $handle, string $description, array $errors): array
	{
		return $this->context([
			'action' => $this->base() . '/create',
			'backUrl' => $this->base(),
			'handle' => $handle,
			'description' => $description,
			'errors' => $errors,
		]);
	}

	private function deleteConfirm(string $menu): string
	{
		$items = (int) $this->row($menu)['items'];

		if ($items === 0) {
			return __('menu:confirm-delete-empty', ['menu' => $menu]);
		}

		return __n('menu:confirm-delete', 'menu:confirm-delete-plural', $items, ['menu' => $menu]);
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

	/**
	 * The menus as the rail renders them. Cached because the rail rides
	 * every context in the area and the entry point reads it too.
	 *
	 * @return list<array{menu: string, description: string, items: int, url: string}>
	 */
	private function menuRows(): array
	{
		if ($this->menuRows !== null) {
			return $this->menuRows;
		}

		$rows = [];

		foreach ($this->db->menus->list()->all() as $row) {
			$menu = (string) $row['menu'];

			$rows[] = [
				'menu' => $menu,
				'description' => (string) $row['description'],
				'items' => (int) $row['items'],
				'url' => $this->url($menu),
			];
		}

		return $this->menuRows = $rows;
	}

	private function row(string $menu): array
	{
		foreach ($this->menuRows() as $row) {
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
