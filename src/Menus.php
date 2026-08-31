<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Quma\Database;
use Cosray\Exception\RuntimeException;
use Cosray\References\Sync;
use Throwable;

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

	/**
	 * @param array<string, string> $description the description per locale
	 * @param ?int $maxDepth how deep the tree may be built, null for unlimited
	 * @param ?Actor $actor who is writing; the system user when nothing says
	 */
	public function create(
		string $menu,
		array $description,
		?int $maxDepth = null,
		?Actor $actor = null,
	): void {
		$usr = ($actor ?? Actor::system())->id;

		$this->db->menus->create([
			'menu' => $menu,
			'description' => json_encode($description),
			'maxDepth' => $maxDepth,
			'creator' => $usr,
			'editor' => $usr,
		])->run();
	}

	/**
	 * @param array<string, string> $description the description per locale
	 * @param ?int $maxDepth how deep the tree may be built, null for unlimited
	 */
	public function update(
		string $menu,
		array $description,
		?int $maxDepth = null,
		?Actor $actor = null,
	): void {
		// A limit shallower than the tree would be inert: nothing rejects the
		// levels that already exist, so refuse it instead of pretending.
		$height = (int) $this->db->menus->menuHeight(['menu' => $menu])->one()['height'];

		if ($maxDepth !== null && $height > $maxDepth) {
			throw new RuntimeException(
				"Menu '{$menu}' is {$height} levels deep and cannot be limited to {$maxDepth}",
			);
		}

		$this->db->menus->update([
			'menu' => $menu,
			'description' => json_encode($description),
			'maxDepth' => $maxDepth,
			'editor' => ($actor ?? Actor::system())->id,
		])->run();
	}

	/**
	 * Renames the menu's handle; the items follow through the FK cascade.
	 * Templates referencing the old handle must be updated by the caller.
	 */
	public function rename(string $menu, string $to): void
	{
		if (!$this->db->menus->exists(['menu' => $menu])->first()) {
			throw new RuntimeException("Menu '{$menu}' does not exist");
		}

		$this->db->menus->rename(['menu' => $menu, 'to' => $to])->run();
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
		bool $hidden = false,
		?Actor $actor = null,
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

		// A fresh item has no children yet, so it adds exactly one level.
		$this->assertDepth($menu, $parent, 1);

		$item ??= $this->uid->generate();
		$usr = ($actor ?? Actor::system())->id;

		$this->db->menus->createItem([
			'item' => $item,
			'parent' => $parent,
			'menu' => $menu,
			'position' => $this->nextPosition($menu, $parent),
			'hidden' => $hidden,
			'data' => json_encode($data),
			'creator' => $usr,
			'editor' => $usr,
		])->run();
		$this->syncReferences($item, $data);

		return $item;
	}

	public function updateItem(
		string $item,
		array $data,
		bool $hidden = false,
		?Actor $actor = null,
	): void {
		if (!is_string($data['type'] ?? null) || $data['type'] === '') {
			throw new RuntimeException('A menu item needs a type');
		}

		$this->itemRow($item);
		$this->db->menus->updateItem([
			'item' => $item,
			'hidden' => $hidden,
			'data' => json_encode($data),
			'editor' => ($actor ?? Actor::system())->id,
		])->run();
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
		$this->assertMove($item, (string) $row['menu'], $parent);

		$this->db->menus->moveItem([
			'item' => $item,
			'parent' => $parent,
			'position' => $position ?? $this->nextPosition((string) $row['menu'], $parent),
		])->run();
	}

	/**
	 * Moves the item below `parent` (or to the root) to the zero-based
	 * `index` among its new siblings and renumbers the whole group 1..n,
	 * giving drag ordering exact semantics on top of `move()`'s looser
	 * sort keys. An out-of-range index clamps to the group's ends.
	 */
	public function place(string $item, ?string $parent, int $index): void
	{
		$row = $this->itemRow($item);
		$this->assertMove($item, (string) $row['menu'], $parent);

		$siblings = array_column(
			$this->db->menus->siblings(['menu' => $row['menu'], 'parent' => $parent])->all(),
			'item',
		);
		$siblings = array_values(array_diff($siblings, [$item]));
		array_splice($siblings, max(0, min($index, count($siblings))), 0, [$item]);

		$owns = !$this->db->getConn()->inTransaction();

		if ($owns) {
			$this->db->begin();
		}

		try {
			foreach ($siblings as $offset => $sibling) {
				$this->db->menus->moveItem([
					'item' => $sibling,
					'parent' => $parent,
					'position' => $offset + 1,
				])->run();
			}

			if ($owns) {
				$this->db->commit();
			}
		} catch (Throwable $e) {
			if ($owns) {
				$this->db->rollback();
			}

			throw $e;
		}
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

	/**
	 * Every precondition a move has to satisfy: the target parent belongs to
	 * the same menu, the move does not create a cycle, and the item's whole
	 * subtree still fits within the menu's `max_depth`.
	 */
	private function assertMove(string $item, string $menu, ?string $parent): void
	{
		if ($parent !== null) {
			if ($this->itemRow($parent)['menu'] !== $menu) {
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

		// A move carries the item's descendants along, so the subtree's height
		// decides whether it fits — not the item alone.
		$this->assertDepth($menu, $parent, $this->itemHeight($item));
	}

	/**
	 * Rejects placing a subtree `$height` levels tall below `$parent` when
	 * that would push its deepest node past the menu's `max_depth`.
	 */
	private function assertDepth(string $menu, ?string $parent, int $height): void
	{
		$max = $this->db->menus->maxDepth(['menu' => $menu])->one()['maxDepth'];

		if ($max === null) {
			return;
		}

		// `ancestors` returns the parent plus everything above it, which is
		// exactly the level the parent sits on; the root is level 0.
		$depth = $parent === null
			? 0
			: count($this->db->menus->ancestors(['item' => $parent])->all());

		if (($depth + $height) > (int) $max) {
			throw new RuntimeException(
				"Menu '{$menu}' allows only {$max} levels",
			);
		}
	}

	private function itemHeight(string $item): int
	{
		return (int) $this->db->menus->itemHeight(['item' => $item])->one()['height'];
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
