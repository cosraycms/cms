<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Services;
use Cosray\Finder\Menu;
use Cosray\Locales;
use Cosray\Menus;
use Cosray\Node\Writer;
use Cosray\Tests\Fixtures\Node\PlainPage;
use Cosray\Tests\IntegrationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class MenusWriterTest extends IntegrationTestCase
{
	private function menus(): Menus
	{
		return new Menus($this->db());
	}

	private function itemData(string $title, string $path): array
	{
		return [
			'type' => 'node',
			'title' => ['en' => $title],
			'path' => ['en' => $path],
		];
	}

	private function insertAsset(string $uid): void
	{
		$this->db()->execute(
			"INSERT INTO cms.assets (uid, disk, key, filename, kind, creator)
			VALUES (:uid, 'local', :key, 'f.pdf', 'file', 1)",
			['uid' => $uid, 'key' => substr($uid, 0, 2) . "/{$uid}/f.pdf"],
		)->run();
	}

	/** @return list<string> */
	private function assetRefs(string $item): array
	{
		return array_column(
			$this->db()->execute(
				"SELECT asset_uid FROM cms.asset_references
				WHERE owner_type = 'menu' AND owner_uid = :item ORDER BY asset_uid",
				['item' => $item],
			)->all(),
			'asset_uid',
		);
	}

	public function testCreatesMenuWithNestedItemsAndFinderRendersThem(): void
	{
		$menus = $this->menus();
		$menus->create('writer-menu', 'Write API test menu');

		$home = $menus->add('writer-menu', $this->itemData('Home', '/'));
		$about = $menus->add('writer-menu', $this->itemData('About', '/about'));
		$team = $menus->add('writer-menu', $this->itemData('Team', '/about/team'), parent: $about);

		$this->assertNotSame($home, $about);
		$this->assertMatchesRegularExpression('/^[123456789bcdfghklmnpqrstvwxyz]{13}$/', $home);

		$menu = new Menu($this->createContext(), 'writer-menu');
		$items = iterator_to_array($menu);

		$this->assertSame([$home, $about], array_slice(array_keys($items), 0, 2));
		$this->assertSame('Home', $items[$home]->title());

		$children = iterator_to_array($items[$about]->children());
		$this->assertCount(1, $children);
		$this->assertSame('Team', $children[0]->title());
		$this->assertSame(2, $children[0]->level());
		$this->assertSame(
			$team,
			$this->db()->execute(
				'SELECT item FROM cms.menu_items WHERE parent = :parent',
				['parent' => $about],
			)->one()['item'],
		);
	}

	public function testPositionsIncrementPerSiblingGroup(): void
	{
		$menus = $this->menus();
		$menus->create('writer-positions', 'Positions');

		$first = $menus->add('writer-positions', $this->itemData('First', '/a'));
		$second = $menus->add('writer-positions', $this->itemData('Second', '/b'));
		$child = $menus->add('writer-positions', $this->itemData('Child', '/c'), parent: $first);

		$positions = array_column(
			$this->db()->execute(
				"SELECT item, position FROM cms.menu_items WHERE menu = 'writer-positions'",
			)->all(),
			'position',
			'item',
		);

		$this->assertSame(1, $positions[$first]);
		$this->assertSame(2, $positions[$second]);
		$this->assertSame(1, $positions[$child]);
	}

	public function testAddRequiresExistingMenu(): void
	{
		$this->throws(RuntimeException::class, "Menu 'missing-menu' does not exist");
		$this->menus()->add('missing-menu', $this->itemData('X', '/x'));
	}

	public function testAddRequiresType(): void
	{
		$this->menus()->create('writer-untyped', 'Untyped');

		$this->throws(RuntimeException::class, 'A menu item needs a type');
		$this->menus()->add('writer-untyped', ['title' => ['en' => 'X']]);
	}

	public function testAddRejectsDottedItemId(): void
	{
		$this->menus()->create('writer-dotted', 'Dotted');

		$this->throws(RuntimeException::class, 'must not contain a dot');
		$this->menus()->add('writer-dotted', $this->itemData('X', '/x'), item: 'about.team');
	}

	public function testAddRejectsParentFromAnotherMenu(): void
	{
		$menus = $this->menus();
		$menus->create('writer-menu-a', 'A');
		$menus->create('writer-menu-b', 'B');
		$parent = $menus->add('writer-menu-a', $this->itemData('A', '/a'));

		$this->throws(RuntimeException::class, 'belongs to another menu');
		$menus->add('writer-menu-b', $this->itemData('B', '/b'), parent: $parent);
	}

	public function testMoveReordersAndReparents(): void
	{
		$menus = $this->menus();
		$menus->create('writer-move', 'Move');
		$first = $menus->add('writer-move', $this->itemData('First', '/a'));
		$second = $menus->add('writer-move', $this->itemData('Second', '/b'));

		// Repositioning before its sibling flips the rendered order.
		$menus->move($second, null, 0);
		$menu = new Menu($this->createContext(), 'writer-move');
		$this->assertSame([$second, $first], array_keys(iterator_to_array($menu)));

		// Reparenting appends below the new parent.
		$menus->move($second, $first);
		$menu = new Menu($this->createContext(), 'writer-move');
		$items = iterator_to_array($menu);
		$this->assertSame([$first], array_keys($items));
		$this->assertTrue($items[$first]->hasChildren());
	}

	public function testMoveRejectsCycles(): void
	{
		$menus = $this->menus();
		$menus->create('writer-cycle', 'Cycle');
		$parent = $menus->add('writer-cycle', $this->itemData('Parent', '/p'));
		$child = $menus->add('writer-cycle', $this->itemData('Child', '/c'), parent: $parent);

		$this->throws(RuntimeException::class, 'below its own descendant');
		$menus->move($parent, $child);
	}

	public function testUpdateItemReplacesData(): void
	{
		$menus = $this->menus();
		$menus->create('writer-update', 'Update');
		$item = $menus->add('writer-update', $this->itemData('Old', '/old'));

		$menus->updateItem($item, $this->itemData('New', '/new'));

		$menu = new Menu($this->createContext(), 'writer-update');
		$this->assertSame('New', iterator_to_array($menu)[$item]->title());
	}

	public function testUpdateItemRequiresExistingItem(): void
	{
		$this->throws(RuntimeException::class, "Menu item 'ghost' does not exist");
		$this->menus()->updateItem('ghost', $this->itemData('X', '/x'));
	}

	public function testRemoveDeletesTheSubtree(): void
	{
		$menus = $this->menus();
		$menus->create('writer-remove', 'Remove');
		$root = $menus->add('writer-remove', $this->itemData('Root', '/'));
		$child = $menus->add('writer-remove', $this->itemData('Child', '/c'), parent: $root);
		$grandchild = $menus->add('writer-remove', $this->itemData('Grandchild', '/g'), parent: $child);
		$other = $menus->add('writer-remove', $this->itemData('Other', '/o'));

		$menus->remove($child);

		$left = array_column(
			$this->db()->execute(
				"SELECT item FROM cms.menu_items WHERE menu = 'writer-remove'",
			)->all(),
			'item',
		);

		$this->assertEqualsCanonicalizing([$root, $other], $left);
		$this->assertNotContains($grandchild, $left);
	}

	public function testNodeItemFollowsTheNodesCurrentPath(): void
	{
		$locales = new Locales();
		$locales->add('en', title: 'English');
		$context = Context::console(
			$this->db(),
			$this->config(),
			$this->container(),
			$this->factory(),
			$locales,
		);
		$services = Services::withDefaults();
		$writer = new Writer($context, new Cms($context, $services), $services->types);
		$writer->create(
			$writer
				->draft(PlainPage::class, ['heading' => 'Target'])
				->uid('menu-node-target')
				->path('en', '/target-current'),
		);

		$menus = $this->menus();
		$menus->create('writer-resolve', 'Resolve');
		$linked = $menus->add('writer-resolve', [
			'type' => 'node',
			'node' => 'menu-node-target',
			'title' => ['en' => 'Target'],
			'path' => ['en' => '/stale-snapshot'],
		]);
		$legacy = $menus->add('writer-resolve', [
			'type' => 'node',
			'node' => 0,
			'title' => ['en' => 'Legacy'],
			'path' => ['en' => '/legacy-snapshot'],
		]);

		$items = iterator_to_array(new Menu($this->createContext(), 'writer-resolve'));

		// The uid-linked item follows the node; the numeric legacy stub
		// keeps its snapshot.
		$this->assertSame('/target-current', $items[$linked]->path());
		$this->assertSame('/legacy-snapshot', $items[$legacy]->path());
	}

	public function testNodeItemFallsBackToSnapshotWhenTheNodeIsGone(): void
	{
		$menus = $this->menus();
		$menus->create('writer-gone', 'Gone');
		$item = $menus->add('writer-gone', [
			'type' => 'node',
			'node' => 'never-existed-uid',
			'title' => ['en' => 'Gone'],
			'path' => ['en' => '/snapshot'],
		]);

		$items = iterator_to_array(new Menu($this->createContext(), 'writer-gone'));

		$this->assertSame('/snapshot', $items[$item]->path());
	}

	public function testItemAssetsEnterAndLeaveTheReferenceIndex(): void
	{
		$this->insertAsset('writer-ref-file');
		$menus = $this->menus();
		$menus->create('writer-refs', 'Refs');
		$item = $menus->add('writer-refs', [
			'type' => 'asset',
			'asset' => 'writer-ref-file',
			'title' => ['en' => 'File'],
		]);

		$this->assertSame(['writer-ref-file'], $this->assetRefs($item));

		// Re-linking the item elsewhere releases the asset again.
		$menus->updateItem($item, $this->itemData('Plain', '/plain'));
		$this->assertSame([], $this->assetRefs($item));

		$menus->updateItem($item, [
			'type' => 'asset',
			'asset' => 'writer-ref-file',
			'title' => ['en' => 'File'],
		]);
		$menus->remove($item);
		$this->assertSame([], $this->assetRefs($item));
	}

	public function testDeleteRemovesMenuAndItems(): void
	{
		$this->insertAsset('writer-del-file');
		$menus = $this->menus();
		$menus->create('writer-delete', 'Delete');
		$root = $menus->add('writer-delete', $this->itemData('Root', '/'));
		$child = $menus->add(
			'writer-delete',
			[
				'type' => 'asset',
				'asset' => 'writer-del-file',
				'title' => ['en' => 'File'],
			],
			parent: $root,
		);

		$menus->delete('writer-delete');

		$this->assertSame([], $this->assetRefs($child));

		$this->assertNull(
			$this->db()->execute(
				"SELECT true AS t FROM cms.menus WHERE menu = 'writer-delete'",
			)->first(),
		);
		$this->assertNull(
			$this->db()->execute(
				"SELECT true AS t FROM cms.menu_items WHERE menu = 'writer-delete'",
			)->first(),
		);
	}
}
