<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Container\Container;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Core\Response;
use Celema\Quma\Database;
use Cosray\Config;
use Cosray\Menus as MenuWriter;
use Cosray\Middleware\Permission;

/**
 * The menus area: listing, menu properties, and deletion. The item tree
 * screen lives beside it in the same area.
 */
final class Menus extends Panel
{
	protected const string AREA = 'menus';

	private const string HANDLE_PATTERN = '/^[a-z0-9-]{1,32}$/';

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

	private function redirect(Factory $factory, string $notice): Response
	{
		return Response::create($factory)->redirect(
			$this->base() . '?notice=' . $notice,
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

	private function base(): string
	{
		return $this->panelPath() . '/menus';
	}

	private function url(string $menu, string $suffix = ''): string
	{
		return $this->base() . '/' . rawurlencode($menu) . $suffix;
	}
}
