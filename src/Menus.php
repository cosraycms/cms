<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Quma\Database;
use Cosray\Exception\RuntimeException;
use Cosray\References\Sync;

/**
 * Write API for menus and their item trees. Reading and rendering stay
 * in `Finder\Menu`.
 *
 * Item ids are generated uids by default. Explicit ids must not contain
 * a dot: the read query builds a dotted path from them, so a dot would
 * corrupt the tree. Item data is the `type`-discriminated jsonb payload
 * that `Finder\MenuItem` reads; content validation stays with the
 * caller.
 *
 * @api
 */
final class Menus
{
	private readonly Sync $sync;

	public function __construct(
		private readonly Database $db,
		private readonly Uid $uid = new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13),
	) {
		$this->sync = new Sync($db);
	}

	public function create(string $menu, string $description): void
	{
		$this->db->menus->create(['menu' => $menu, 'description' => $description])->run();
	}

	public function update(string $menu, string $description): void
	{
		$this->db->menus->update(['menu' => $menu, 'description' => $description])->run();
	}

	/** Deletes the menu including all of its items. */
	public function delete(string $menu): void
	{
		foreach ($this->db->menus->deleteItems(['menu' => $menu])->all() as $row) {
			$this->sync->remove('menu', (string) $row['item']);
		}

		$this->db->menus->delete(['menu' => $menu])->run();
	}

	/**
	 * Appends an item to the menu, below `parent` or at the root, and
	 * returns its id.
	 */
	public function add(
		string $menu,
		array $data,
		?string $parent = null,
		?string $item = null,
	): string {
		if (!is_string($data['type'] ?? null) || $data['type'] === '') {
			throw new RuntimeException('A menu item needs a type');
		}

		if (!$this->db->menus->exists(['menu' => $menu])->first()) {
			throw new RuntimeException("Menu '{$menu}' does not exist");
		}

		if ($parent !== null && $this->itemRow($parent)['menu'] !== $menu) {
			throw new RuntimeException(
				"Parent item '{$parent}' belongs to another menu",
			);
		}

		if ($item !== null && str_contains($item, '.')) {
			throw new RuntimeException('A menu item id must not contain a dot');
		}

		$item ??= $this->uid->generate();

		$this->db->menus->createItem([
			'item' => $item,
			'parent' => $parent,
			'menu' => $menu,
			'position' => $this->nextPosition($menu, $parent),
			'data' => json_encode($data),
		])->run();
		$this->syncReferences($item, $data);

		return $item;
	}

	public function updateItem(string $item, array $data): void
	{
		if (!is_string($data['type'] ?? null) || $data['type'] === '') {
			throw new RuntimeException('A menu item needs a type');
		}

		$this->itemRow($item);
		$this->db->menus->updateItem(['item' => $item, 'data' => json_encode($data)])->run();
		$this->syncReferences($item, $data);
	}

	/**
	 * Moves the item below `parent` (or to the root), at `position` or
	 * appended to its new siblings. Positions are sort keys, not indexes;
	 * they may repeat, and ties order by item id.
	 */
	public function move(string $item, ?string $parent, ?int $position = null): void
	{
		$row = $this->itemRow($item);

		if ($parent !== null) {
			$parentRow = $this->itemRow($parent);

			if ($parentRow['menu'] !== $row['menu']) {
				throw new RuntimeException(
					"Parent item '{$parent}' belongs to another menu",
				);
			}

			$ancestors = array_column(
				$this->db->menus->ancestors(['item' => $parent])->all(),
				'item',
			);

			if (in_array($item, $ancestors, true)) {
				throw new RuntimeException(
					"Cannot move '{$item}' below its own descendant '{$parent}'",
				);
			}
		}

		$this->db->menus->moveItem([
			'item' => $item,
			'parent' => $parent,
			'position' => $position ?? $this->nextPosition((string) $row['menu'], $parent),
		])->run();
	}

	/** Deletes the item including all of its descendants. */
	public function remove(string $item): void
	{
		$this->itemRow($item);

		foreach ($this->db->menus->deleteItemTree(['item' => $item])->all() as $row) {
			$this->sync->remove('menu', (string) $row['item']);
		}
	}

	/**
	 * Keeps the derived asset reference index in step with the item's
	 * `image` icon and `asset` link target, mirroring what the rebuild
	 * derives from stored menu rows.
	 */
	private function syncReferences(string $item, array $data): void
	{
		$assets = [];

		foreach (['image', 'asset'] as $key) {
			$uid = $data[$key] ?? null;

			if (is_string($uid) && $uid !== '') {
				$assets[] = $uid;
			}
		}

		$this->sync->replace('menu', $item, ['assets' => $assets, 'nodes' => []]);
	}

	private function itemRow(string $item): array
	{
		$row = $this->db->menus->itemRow(['item' => $item])->first();

		if (!$row) {
			throw new RuntimeException("Menu item '{$item}' does not exist");
		}

		return $row;
	}

	private function nextPosition(string $menu, ?string $parent): int
	{
		$row = $this->db->menus->maxPosition(['menu' => $menu, 'parent' => $parent])->one();

		return (int) $row['position'] + 1;
	}
}
