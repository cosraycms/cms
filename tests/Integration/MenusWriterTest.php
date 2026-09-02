<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Container\Container;
use Cosray\Actor;
use Cosray\Bootstrap;
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
use PDOException;

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
			"INSERT INTO cms.assets (uid, disk, key, filename, creator)
			VALUES (:uid, 'local', :key, 'f.pdf', 1)",
			['uid' => $uid, 'key' => substr($uid, 0, 2) . "/{$uid}/f.pdf"],
		)->run();
	}

	private function insertUser(string $name): int
	{
		return (int) $this->db()->execute(
			"INSERT INTO cms.users
			(uid, username, email, password, rolename, active, data, creator, editor)
			VALUES (:uid, :name, :email, 'x', 'editor', true, '{}'::jsonb, 1, 1)
			RETURNING usr",
			['uid' => $name, 'name' => $name, 'email' => $name . '@example.test'],
		)->one()['usr'];
	}

	/** @return array{creator: int, editor: int, created: string, changed: string} */
	private function auditRow(string $menu, ?string $item = null): array
	{
		$row = $item === null
			? $this->db()->execute(
				'SELECT creator, editor, created, changed FROM cms.menus WHERE menu = :menu',
				['menu' => $menu],
			)->one()
			: $this->db()->execute(
				'SELECT creator, editor, created, changed FROM cms.menu_items WHERE item = :item',
				['item' => $item],
			)->one();

		return [
			'creator' => (int) $row['creator'],
			'editor' => (int) $row['editor'],
			'created' => (string) $row['created'],
			'changed' => (string) $row['changed'],
		];
	}

	/** @return list<string> */
	private function nodeRefs(string $item): array
	{
		return array_column(
			$this->db()->execute(
				"SELECT target_uid FROM cms.node_references
				WHERE owner_type = 'menu' AND owner_uid = :item ORDER BY target_uid",
				['item' => $item],
			)->all(),
			'target_uid',
		);
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
		$menus->create('writer-menu', ['zxx' => 'Write API test menu']);

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
		$menus->create('writer-positions', ['zxx' => 'Positions']);

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

	public function testRenameMovesTheHandleWithItsItems(): void
	{
		$menus = $this->menus();
		$menus->create('writer-rename', ['zxx' => 'Rename']);
		$item = $menus->add('writer-rename', $this->itemData('Home', '/'));

		$menus->rename('writer-rename', 'writer-renamed');

		$this->assertSame(
			[$item],
			array_keys(iterator_to_array(new Menu($this->createContext(), 'writer-renamed'))),
		);
		$this->throws(RuntimeException::class, "Menu 'writer-rename' not found");
		new Menu($this->createContext(), 'writer-rename');
	}

	public function testRenameRequiresExistingMenu(): void
	{
		$this->throws(RuntimeException::class, "Menu 'writer-ghost' does not exist");
		$this->menus()->rename('writer-ghost', 'writer-other');
	}

	public function testAddRequiresExistingMenu(): void
	{
		$this->throws(RuntimeException::class, "Menu 'missing-menu' does not exist");
		$this->menus()->add('missing-menu', $this->itemData('X', '/x'));
	}

	public function testAddRequiresType(): void
	{
		$this->menus()->create('writer-untyped', ['zxx' => 'Untyped']);

		$this->throws(RuntimeException::class, 'A menu item needs a type');
		$this->menus()->add('writer-untyped', ['title' => ['en' => 'X']]);
	}

	public function testAddRejectsDottedItemId(): void
	{
		$this->menus()->create('writer-dotted', ['zxx' => 'Dotted']);

		$this->throws(RuntimeException::class, 'must not contain a dot');
		$this->menus()->add('writer-dotted', $this->itemData('X', '/x'), item: 'about.team');
	}

	public function testAddRejectsParentFromAnotherMenu(): void
	{
		$menus = $this->menus();
		$menus->create('writer-menu-a', ['zxx' => 'A']);
		$menus->create('writer-menu-b', ['zxx' => 'B']);
		$parent = $menus->add('writer-menu-a', $this->itemData('A', '/a'));

		$this->throws(RuntimeException::class, 'belongs to another menu');
		$menus->add('writer-menu-b', $this->itemData('B', '/b'), parent: $parent);
	}

	public function testWritesRecordTheirActor(): void
	{
		$editor = $this->insertUser('writer-audit-editor');
		$menus = $this->menus();
		$menus->create('writer-audit', ['zxx' => 'Audit'], actor: new Actor($editor));
		$item = $menus->add('writer-audit', $this->itemData('X', '/x'), actor: new Actor($editor));

		$before = $this->auditRow('writer-audit');
		$this->assertSame($editor, $before['creator']);
		$this->assertSame($editor, $before['editor']);
		$this->assertNotSame('', $before['created']);

		// A later edit by someone else keeps the creator and moves the editor.
		$menus->update('writer-audit', ['zxx' => 'Audit'], actor: new Actor(1));
		$menus->updateItem($item, $this->itemData('Y', '/y'), actor: new Actor(1));

		$after = $this->auditRow('writer-audit');
		$this->assertSame($editor, $after['creator']);
		$this->assertSame(1, $after['editor']);
		// `changed` cannot be asserted to advance here: the shared trigger uses
		// `now()`, which is the transaction's start time, and these tests run
		// inside one.
		$this->assertNotSame('', $after['changed']);

		$itemRow = $this->auditRow('writer-audit', $item);
		$this->assertSame($editor, $itemRow['creator']);
		$this->assertSame(1, $itemRow['editor']);
	}

	public function testAWriteWithoutAnActorIsTheSystemUser(): void
	{
		// The path every site migration and import takes.
		$menus = $this->menus();
		$menus->create('writer-system', ['zxx' => 'System']);

		$this->assertSame(1, $this->auditRow('writer-system')['creator']);
	}

	public function testTheDatabaseRefusesACrossMenuParentOnItsOwn(): void
	{
		// The write API guards this, so the constraint is what covers raw SQL:
		// imports, site migrations, and anything else bypassing `Menus`.
		$menus = $this->menus();
		$menus->create('writer-fk-a', ['zxx' => 'A']);
		$menus->create('writer-fk-b', ['zxx' => 'B']);
		$parent = $menus->add('writer-fk-a', $this->itemData('A', '/a'));

		$this->throws(PDOException::class);
		$this->db()->execute(
			'INSERT INTO cms.menu_items (item, parent, menu, position, data)
			VALUES (:item, :parent, :menu, 1, :data::jsonb)',
			[
				'item' => 'writer-fk-orphan',
				'parent' => $parent,
				'menu' => 'writer-fk-b',
				'data' => json_encode($this->itemData('B', '/b')),
			],
		)->run();
	}

	public function testMoveReordersAndReparents(): void
	{
		$menus = $this->menus();
		$menus->create('writer-move', ['zxx' => 'Move']);
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

	public function testPlacePutsTheItemAtItsIndexAndRenumbersTheGroup(): void
	{
		$menus = $this->menus();
		$menus->create('writer-place', ['zxx' => 'Place']);
		$a = $menus->add('writer-place', $this->itemData('A', '/a'));
		$b = $menus->add('writer-place', $this->itemData('B', '/b'));
		$c = $menus->add('writer-place', $this->itemData('C', '/c'));

		$menus->place($c, null, 0);

		$this->assertSame(
			[$c, $a, $b],
			array_keys(iterator_to_array(new Menu($this->createContext(), 'writer-place'))),
		);
		$this->assertSame(
			[1, 2, 3],
			array_column(
				$this->db()->execute(
					"SELECT position FROM cms.menu_items
					WHERE menu = 'writer-place' ORDER BY position",
				)->all(),
				'position',
			),
		);

		// An index past the end clamps to the last slot.
		$menus->place($c, null, 99);
		$this->assertSame(
			[$a, $b, $c],
			array_keys(iterator_to_array(new Menu($this->createContext(), 'writer-place'))),
		);
	}

	public function testPlaceReparentsAtTheGivenIndex(): void
	{
		$menus = $this->menus();
		$menus->create('writer-place-nest', ['zxx' => 'Place nested']);
		$parent = $menus->add('writer-place-nest', $this->itemData('Parent', '/p'));
		$menus->add('writer-place-nest', $this->itemData('X', '/x'), parent: $parent);
		$menus->add('writer-place-nest', $this->itemData('Y', '/y'), parent: $parent);
		$moved = $menus->add('writer-place-nest', $this->itemData('Moved', '/m'));

		$menus->place($moved, $parent, 1);

		$items = iterator_to_array(new Menu($this->createContext(), 'writer-place-nest'));
		$children = array_map(
			static fn($child) => $child->title(),
			iterator_to_array($items[$parent]->children()),
		);
		$this->assertSame(['X', 'Moved', 'Y'], $children);
	}

	public function testPlaceRejectsCycles(): void
	{
		$menus = $this->menus();
		$menus->create('writer-place-cycle', ['zxx' => 'Place cycle']);
		$parent = $menus->add('writer-place-cycle', $this->itemData('Parent', '/p'));
		$child = $menus->add('writer-place-cycle', $this->itemData('Child', '/c'), parent: $parent);

		$this->throws(RuntimeException::class, 'below its own descendant');
		$menus->place($parent, $child, 0);
	}

	public function testAddRejectsAnItemPastTheMaxDepth(): void
	{
		$menus = $this->menus();
		$menus->create('writer-depth-add', ['zxx' => 'Depth'], 2);
		$parent = $menus->add('writer-depth-add', $this->itemData('Parent', '/p'));
		$child = $menus->add('writer-depth-add', $this->itemData('Child', '/c'), parent: $parent);

		$this->throws(RuntimeException::class, 'allows only 2 levels');
		$menus->add('writer-depth-add', $this->itemData('Deep', '/d'), parent: $child);
	}

	public function testAMoveIsMeasuredByTheWholeSubtreesHeight(): void
	{
		$menus = $this->menus();
		$menus->create('writer-depth-move', ['zxx' => 'Depth move'], 2);
		$host = $menus->add('writer-depth-move', $this->itemData('Host', '/h'));
		$branch = $menus->add('writer-depth-move', $this->itemData('Branch', '/b'));
		$menus->add('writer-depth-move', $this->itemData('Leaf', '/l'), parent: $branch);

		// The branch itself would land on level 2, but it carries a child that
		// would end up on level 3.
		$this->throws(RuntimeException::class, 'allows only 2 levels');
		$menus->place($branch, $host, 0);
	}

	public function testAnUnlimitedMenuTakesAnyDepth(): void
	{
		$menus = $this->menus();
		$menus->create('writer-depth-free', ['zxx' => 'No limit']);
		$parent = $menus->add('writer-depth-free', $this->itemData('One', '/1'));
		$child = $menus->add('writer-depth-free', $this->itemData('Two', '/2'), parent: $parent);
		$grand = $menus->add('writer-depth-free', $this->itemData('Three', '/3'), parent: $child);

		$this->assertSame(
			'writer-depth-free',
			$this->db()->execute(
				'SELECT menu FROM cms.menu_items WHERE item = :item',
				['item' => $grand],
			)->one()['menu'],
		);
	}

	public function testTheLimitCannotBeSetBelowTheExistingTree(): void
	{
		$menus = $this->menus();
		$menus->create('writer-depth-late', ['zxx' => 'Late limit']);
		$parent = $menus->add('writer-depth-late', $this->itemData('Parent', '/p'));
		$menus->add('writer-depth-late', $this->itemData('Child', '/c'), parent: $parent);

		// Two levels is fine, one would leave the limit inert.
		$menus->update('writer-depth-late', ['zxx' => 'Late limit'], 2);

		$this->throws(RuntimeException::class, 'is 2 levels deep and cannot be limited to 1');
		$menus->update('writer-depth-late', ['zxx' => 'Late limit'], 1);
	}

	public function testMoveRejectsCycles(): void
	{
		$menus = $this->menus();
		$menus->create('writer-cycle', ['zxx' => 'Cycle']);
		$parent = $menus->add('writer-cycle', $this->itemData('Parent', '/p'));
		$child = $menus->add('writer-cycle', $this->itemData('Child', '/c'), parent: $parent);

		$this->throws(RuntimeException::class, 'below its own descendant');
		$menus->move($parent, $child);
	}

	public function testUpdateItemReplacesData(): void
	{
		$menus = $this->menus();
		$menus->create('writer-update', ['zxx' => 'Update']);
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
		$menus->create('writer-remove', ['zxx' => 'Remove']);
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

	private function writeNode(
		string $uid,
		string $heading,
		string $path,
		?string $parent = null,
		bool $published = true,
	): void {
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
				->draft(PlainPage::class, ['heading' => $heading])
				->uid($uid)
				->parent($parent)
				->published($published)
				->path('en', $path),
		);
	}

	/**
	 * Hydrating written nodes resolves their class by type handle through
	 * the container, so the fixture type joins the pre-registered set.
	 */
	public function container(): Container
	{
		$container = parent::container();
		$container->tag(Bootstrap::NODE_TAG)->add('plain-page', PlainPage::class);

		return $container;
	}

	public function testNodeItemFollowsTheNodesCurrentPath(): void
	{
		$this->writeNode('menu-node-target', 'Target', '/target-current');

		$menus = $this->menus();
		$menus->create('writer-resolve', ['zxx' => 'Resolve']);
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

	public function testNodeItemWithoutTitleInheritsTheNodesTitle(): void
	{
		$this->writeNode('menu-title-source', 'Fresh Title', '/title-source');

		$menus = $this->menus();
		$menus->create('writer-title', ['zxx' => 'Title']);
		$inherits = $menus->add('writer-title', [
			'type' => 'node',
			'node' => 'menu-title-source',
		]);
		$overrides = $menus->add('writer-title', [
			'type' => 'node',
			'node' => 'menu-title-source',
			'title' => ['en' => 'Custom'],
		]);

		$items = iterator_to_array(new Menu($this->createContext(), 'writer-title'));

		$this->assertSame('Fresh Title', $items[$inherits]->title());
		$this->assertSame('Custom', $items[$overrides]->title());
	}

	public function testChildrenItemExpandsIntoTheNodesChildren(): void
	{
		$this->writeNode('menu-kids-root', 'Kids Root', '/kids');
		$this->writeNode('menu-kids-beta', 'Beta', '/kids/beta', parent: 'menu-kids-root');
		$this->writeNode('menu-kids-alpha', 'Alpha', '/kids/alpha', parent: 'menu-kids-root');
		$this->writeNode('menu-kids-draft', 'Draft', '/kids/draft', parent: 'menu-kids-root', published: false);

		$menus = $this->menus();
		$menus->create('writer-children', ['zxx' => 'Children']);
		$before = $menus->add('writer-children', $this->itemData('Before', '/before'));
		$menus->add('writer-children', ['type' => 'children', 'node' => 'menu-kids-root']);

		$menu = $this->createCms()->menu('writer-children');
		$items = iterator_to_array($menu);

		// Expanded in place: the static item first, then the published
		// children ordered by title; the draft stays out.
		$this->assertSame(
			[$before, 'children:menu-kids-alpha', 'children:menu-kids-beta'],
			array_keys($items),
		);
		$this->assertSame('Alpha', $items['children:menu-kids-alpha']->title());
		$this->assertSame('/kids/alpha', $items['children:menu-kids-alpha']->path());
		$this->assertSame(1, $items['children:menu-kids-alpha']->level());

		$html = $menu->html();
		$this->assertStringContainsString('<a href="/kids/alpha">', $html);
		$this->assertStringNotContainsString('Draft', $html);
	}

	public function testChildrenItemDescendsTheConfiguredLevels(): void
	{
		$this->writeNode('menu-deep-root', 'Deep Root', '/deep');
		$this->writeNode('menu-deep-child', 'Child', '/deep/child', parent: 'menu-deep-root');
		$this->writeNode('menu-deep-grand', 'Grand', '/deep/child/grand', parent: 'menu-deep-child');

		$menus = $this->menus();
		$menus->create('writer-levels', ['zxx' => 'Levels']);
		$menus->add('writer-levels', [
			'type' => 'children',
			'node' => 'menu-deep-root',
			'levels' => 2,
		]);

		$items = iterator_to_array($this->createCms()->menu('writer-levels'));
		$child = $items['children:menu-deep-child'];
		$grand = iterator_to_array($child->children());

		$this->assertTrue($child->hasChildren());
		$this->assertCount(1, $grand);
		$this->assertSame('Grand', $grand[0]->title());
		$this->assertSame(2, $grand[0]->level());

		// The default depth of one stops above the grandchild.
		$menus->create('writer-levels-flat', ['zxx' => 'Flat']);
		$menus->add('writer-levels-flat', [
			'type' => 'children',
			'node' => 'menu-deep-root',
		]);
		$flat = iterator_to_array($this->createCms()->menu('writer-levels-flat'));
		$this->assertFalse($flat['children:menu-deep-child']->hasChildren());
	}

	public function testUnexpandedMenuKeepsChildrenItemsAsStored(): void
	{
		$menus = $this->menus();
		$menus->create('writer-children-raw', ['zxx' => 'Raw']);
		$item = $menus->add('writer-children-raw', [
			'type' => 'children',
			'node' => 'some-node-uid',
		]);

		$items = iterator_to_array(new Menu($this->createContext(), 'writer-children-raw', expand: false));

		$this->assertArrayHasKey($item, $items);
		$this->assertSame('children', $items[$item]->type());
		$this->assertSame('some-node-uid', $items[$item]->node());
	}

	public function testChildrenItemWithoutACmsExpandsToNothing(): void
	{
		$menus = $this->menus();
		$menus->create('writer-children-bare', ['zxx' => 'Bare']);
		$menus->add('writer-children-bare', ['type' => 'children', 'node' => 'whatever']);
		$kept = $menus->add('writer-children-bare', $this->itemData('Kept', '/kept'));

		$items = iterator_to_array(new Menu($this->createContext(), 'writer-children-bare'));

		$this->assertSame([$kept], array_keys($items));
	}

	public function testNodeItemFallsBackToSnapshotWhenTheNodeIsGone(): void
	{
		$menus = $this->menus();
		$menus->create('writer-gone', ['zxx' => 'Gone']);
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
		$menus->create('writer-refs', ['zxx' => 'Refs']);
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

	public function testLinkedNodesEnterAndLeaveTheReferenceIndex(): void
	{
		$this->writeNode('writer-ref-node', 'Target', '/ref-target');
		$menus = $this->menus();
		$menus->create('writer-node-refs', ['zxx' => 'Node refs']);
		$item = $menus->add('writer-node-refs', [
			'type' => 'node',
			'node' => 'writer-ref-node',
			'title' => ['en' => 'Linked'],
		]);

		$this->assertSame(['writer-ref-node'], $this->nodeRefs($item));

		// Retargeting to a plain URL releases the node again.
		$menus->updateItem($item, ['type' => 'url', 'path' => ['en' => '/plain']]);
		$this->assertSame([], $this->nodeRefs($item));

		// A uid nothing resolves to is skipped rather than tripping the FK.
		$menus->updateItem($item, ['type' => 'node', 'node' => 'writer-ref-vanished']);
		$this->assertSame([], $this->nodeRefs($item));

		$menus->updateItem($item, ['type' => 'node', 'node' => 'writer-ref-node']);
		$menus->remove($item);
		$this->assertSame([], $this->nodeRefs($item));
	}

	public function testDeleteRemovesMenuAndItems(): void
	{
		$this->insertAsset('writer-del-file');
		$menus = $this->menus();
		$menus->create('writer-delete', ['zxx' => 'Delete']);
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
