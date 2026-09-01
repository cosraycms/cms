<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Node\TestHierarchyParent;

/**
 * The menu item tree screen with its editing side pane: selection and
 * creation via URL state, item CRUD, and reordering.
 *
 * @internal
 *
 * @coversNothing
 */
final class PanelMenuTreeTest extends End2EndTestCase
{
	private int $nodeTypeId;

	protected function setUp(): void
	{
		parent::setUp();
		$this->authenticateAs('admin');
		$this->nodeTypeId = $this->createTestType('test-hierarchy-parent');
		$this->db()->execute(
			"INSERT INTO cms.menus (menu, description) VALUES ('tree-menu', '{\"zxx\": \"Tree menu\"}')",
		)->run();
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$plugin = parent::createBootstrap($config);
		$plugin->node(TestHierarchyParent::class);

		return $plugin;
	}

	private function createItem(
		string $item,
		?string $parent = null,
		int $position = 1,
		?array $data = null,
	): void {
		$this->db()->execute(
			'INSERT INTO cms.menu_items (item, parent, menu, position, data)
			VALUES (:item, :parent, :menu, :position, :data::jsonb)',
			[
				'item' => $item,
				'parent' => $parent,
				'menu' => 'tree-menu',
				'position' => $position,
				'data' => json_encode(
					$data ?? [
						'type' => 'url',
						'title' => ['en' => 'Item ' . $item],
						'path' => ['en' => '/' . $item],
					],
				),
			],
		)->run();
	}

	private function insertAsset(string $uid): void
	{
		$this->db()->execute(
			"INSERT INTO cms.assets (uid, disk, key, filename, creator)
			VALUES (:uid, 'local', :key, 'file.pdf', 1)",
			['uid' => $uid, 'key' => substr($uid, 0, 2) . "/{$uid}/file.pdf"],
		)->run();
	}

	/** @return list<string> item ids of one sibling group in stored order */
	private function order(?string $parent = null): array
	{
		return array_column(
			$this->db()->execute(
				"SELECT item FROM cms.menu_items
				WHERE menu = 'tree-menu' AND parent IS NOT DISTINCT FROM :parent
				ORDER BY position, item",
				['parent' => $parent],
			)->all(),
			'item',
		);
	}

	private function itemData(string $item): array
	{
		return json_decode(
			(string) $this->db()->execute(
				'SELECT data FROM cms.menu_items WHERE item = :item',
				['item' => $item],
			)->one()['data'],
			true,
		);
	}

	public function testTreeRendersNestedCardsWithActions(): void
	{
		$this->createItem('root-a');
		$this->createItem('child-a', 'root-a');
		$this->createItem('grandchild-a', 'child-a');
		$this->createItem('root-b', position: 2);

		$response = $this->makeRequest('GET', '/cp/menus/tree-menu');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('data-uid="root-a"', $html);
		$this->assertStringContainsString('class="menu-children"', $html);
		$this->assertStringContainsString('Item grandchild-a', $html);
		$this->assertStringContainsString('?item=root-a', $html);
		$this->assertStringContainsString('?add=root-a', $html);
		$this->assertStringContainsString('/cp/menus/tree-menu/item/root-a/move', $html);
		// The delete confirm counts the whole subtree below the item.
		$this->assertStringContainsString(
			'and its 2 child items',
			$html,
		);
		$this->assertStringContainsString('Delete &quot;Item root-b&quot;?', $html);
	}

	public function testSelectingAnItemFillsThePane(): void
	{
		$this->createItem('selected-item', null, 1, [
			'type' => 'url',
			'title' => ['en' => 'Selected'],
			'path' => ['en' => '/selected'],
			'class' => 'promoted',
			'target' => '_blank',
		]);

		$html = $this->getHtmlResponse(
			$this->makeRequest('GET', '/cp/menus/tree-menu?item=selected-item'),
		);

		$this->assertHtmlNodeExists(
			'//li[@role="treeitem" and @data-uid="selected-item"]/div[contains(concat(" ", normalize-space(@class), " "), " is-selected ")]',
			$html,
		);
		$form = '//form[@action="/cp/menus/tree-menu/item/selected-item"]';
		$this->assertHtmlNodeExists("{$form}//input[@name=\"title[en]\" and @value=\"Selected\"]", $html);
		$this->assertHtmlNodeExists("{$form}//input[@name=\"path[en]\" and @value=\"/selected\"]", $html);
		$this->assertHtmlNodeExists("{$form}//input[@name=\"class\" and @value=\"promoted\"]", $html);
		$this->assertHtmlNodeExists(
			"{$form}//input[@type=\"checkbox\" and @name=\"target\" and @value=\"_blank\" and @checked]",
			$html,
		);
	}

	public function testInsertingBelowASiblingLandsItThere(): void
	{
		$this->createItem('ins-a', null, 1);
		$this->createItem('ins-b', null, 2);
		$this->createItem('ins-c', null, 3);

		// The kebab link carries the anchor into the create pane.
		$pane = $this->getHtmlResponse(
			$this->makeRequest('GET', '/cp/menus/tree-menu?after=ins-a'),
		);
		$this->assertHtmlNodeExists(
			'//form[@action="/cp/menus/tree-menu/item/create"]//input[@name="after" and @value="ins-a"]',
			$pane,
		);
		// A root anchor means no parent; the drag form's own empty one aside.
		$this->assertHtmlNodeMissing(
			'//form[@action="/cp/menus/tree-menu/item/create"]//input[@name="parent" and starts-with(@value, "ins-")]',
			$pane,
		);

		$response = $this->makeRequest('POST', '/cp/menus/tree-menu/item/create', [
			'body' => [
				'type' => 'url',
				'title' => ['en' => 'Inserted'],
				'path' => ['en' => '/inserted'],
				'after' => 'ins-a',
			],
		]);

		$this->assertResponseStatus(303, $response);
		$order = $this->order();
		$this->assertSame(['ins-a', $order[1], 'ins-b', 'ins-c'], $order);
		$this->assertSame('Inserted', $this->itemData($order[1])['title']['en']);
	}

	public function testInsertingAboveASiblingLandsItThere(): void
	{
		$this->createItem('above-a', null, 1);
		$this->createItem('above-b', null, 2);

		$pane = $this->getHtmlResponse(
			$this->makeRequest('GET', '/cp/menus/tree-menu?before=above-a'),
		);
		$this->assertHtmlNodeExists(
			'//form[@action="/cp/menus/tree-menu/item/create"]//input[@name="before" and @value="above-a"]',
			$pane,
		);

		$this->makeRequest('POST', '/cp/menus/tree-menu/item/create', [
			'body' => [
				'type' => 'url',
				'title' => ['en' => 'First now'],
				'path' => ['en' => '/first-now'],
				'before' => 'above-a',
			],
		]);

		// The one thing the root add cannot do: a new first item.
		$order = $this->order();
		$this->assertSame([$order[0], 'above-a', 'above-b'], $order);
		$this->assertSame('First now', $this->itemData($order[0])['title']['en']);
	}

	public function testInsertingBelowAChildStaysInThatGroup(): void
	{
		$this->createItem('ins-parent', null, 1);
		$this->createItem('ins-child', 'ins-parent', 1);

		$pane = $this->getHtmlResponse(
			$this->makeRequest('GET', '/cp/menus/tree-menu?after=ins-child'),
		);
		// The anchor's group, not the anchor itself, is the new parent.
		$this->assertHtmlNodeExists(
			'//form[@action="/cp/menus/tree-menu/item/create"]//input[@name="parent" and @value="ins-parent"]',
			$pane,
		);

		$this->makeRequest('POST', '/cp/menus/tree-menu/item/create', [
			'body' => [
				'type' => 'url',
				'title' => ['en' => 'Sibling'],
				'path' => ['en' => '/sibling'],
				'parent' => 'ins-parent',
				'after' => 'ins-child',
			],
		]);

		$this->assertSame(['ins-parent'], $this->order());
		$children = $this->order('ins-parent');
		$this->assertCount(2, $children);
		$this->assertSame('ins-child', $children[0]);
	}

	public function testInsertingBelowAnItemOfAnotherMenuIs404(): void
	{
		$this->db()->execute(
			"INSERT INTO cms.menus (menu, description) VALUES ('other-anchor', '{\"zxx\": \"Other\"}')",
		)->run();
		$this->db()->execute(
			"INSERT INTO cms.menu_items (item, menu, position, data)
			VALUES ('foreign-anchor', 'other-anchor', 1, '{\"type\": \"label\"}'::jsonb)",
		)->run();

		$this->assertResponseStatus(
			404,
			$this->makeRequest('GET', '/cp/menus/tree-menu?after=foreign-anchor'),
		);
	}

	public function testTheRootAddActionStaysReachableWhileEditing(): void
	{
		$this->createItem('busy-item');

		foreach (['', '?item=busy-item', '?add=busy-item'] as $state) {
			$html = $this->getHtmlResponse(
				$this->makeRequest('GET', '/cp/menus/tree-menu' . $state),
			);

			$this->assertHtmlNodeExists(
				'//a[@href="/cp/menus/tree-menu?add=" and contains(concat(" ", normalize-space(@class), " "), " add ")]',
				$html,
			);
		}
	}

	public function testAddPaneForRootAndBelowAParent(): void
	{
		$this->createItem('add-parent', null, 1, [
			'type' => 'url',
			'title' => ['en' => 'The Parent'],
			'path' => ['en' => '/parent'],
		]);

		$root = $this->getHtmlResponse($this->makeRequest('GET', '/cp/menus/tree-menu?add='));
		$this->assertStringContainsString('action="/cp/menus/tree-menu/item/create"', $root);
		// Only the drag form carries a parent input (empty); the pane's
		// create form preselects none.
		$this->assertStringNotContainsString('name="parent" value="add-parent"', $root);

		$below = $this->getHtmlResponse(
			$this->makeRequest('GET', '/cp/menus/tree-menu?add=add-parent'),
		);
		$this->assertStringContainsString('name="parent" value="add-parent"', $below);
		$this->assertStringContainsString('Below &quot;The Parent&quot;', $below);
	}

	public function testCreatesUrlItemsAppended(): void
	{
		$response = $this->makeRequest('POST', '/cp/menus/tree-menu/item/create', [
			'body' => ['type' => 'url', 'title' => ['en' => 'First'], 'path' => ['en' => '/first']],
		]);

		$this->assertResponseStatus(303, $response);
		$location = $response->getHeaderLine('Location');
		$this->assertStringContainsString('/cp/menus/tree-menu?item=', $location);
		$this->assertStringContainsString('notice=item-created', $location);

		$this->makeRequest('POST', '/cp/menus/tree-menu/item/create', [
			'body' => ['type' => 'url', 'title' => ['en' => 'Second'], 'path' => ['en' => '/second']],
		]);

		$order = $this->order();
		$this->assertCount(2, $order);
		$this->assertSame('First', $this->itemData($order[0])['title']['en']);
		$this->assertSame('Second', $this->itemData($order[1])['title']['en']);
	}

	public function testCreatesNodeItemAndRequiresAnExistingNode(): void
	{
		$rejected = $this->makeRequest('POST', '/cp/menus/tree-menu/item/create', [
			'body' => ['type' => 'node', 'node' => 'never-was'],
		]);
		$this->assertResponseOk($rejected);
		$this->assertStringContainsString(
			'Pick an existing page.',
			$this->getHtmlResponse($rejected),
		);
		$this->assertSame([], $this->order());

		$this->createTestNode([
			'uid' => 'menu-tree-target',
			'type' => $this->nodeTypeId,
			'published' => true,
			'content' => ['title' => ['type' => 'text', 'value' => ['en' => 'Target']]],
		]);

		$accepted = $this->makeRequest('POST', '/cp/menus/tree-menu/item/create', [
			'body' => ['type' => 'node', 'node' => 'menu-tree-target'],
		]);
		$this->assertResponseStatus(303, $accepted);

		$data = $this->itemData($this->order()[0]);
		$this->assertSame('node', $data['type']);
		$this->assertSame('menu-tree-target', $data['node']);
		$this->assertArrayNotHasKey('title', $data);
	}

	public function testUpdateRewritesTheItemPayload(): void
	{
		$this->createItem('rewrite-me');
		$this->insertAsset('menu-tree-icon');

		$response = $this->makeRequest('POST', '/cp/menus/tree-menu/item/rewrite-me', [
			'body' => [
				'type' => 'url',
				'title' => ['en' => 'Renamed'],
				'path' => ['en' => 'https://example.com/'],
				'target' => '_blank',
				'class' => 'external',
				'image' => 'menu-tree-icon',
			],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString('notice=item-saved', $response->getHeaderLine('Location'));
		// jsonb storage reorders keys, so compare order-insensitively.
		$this->assertEquals(
			[
				'type' => 'url',
				'title' => ['en' => 'Renamed'],
				'path' => ['en' => 'https://example.com/'],
				'target' => '_blank',
				'class' => 'external',
				'image' => 'menu-tree-icon',
			],
			$this->itemData('rewrite-me'),
		);
	}

	public function testRejectsMalformedUrls(): void
	{
		$response = $this->makeRequest('POST', '/cp/menus/tree-menu/item/create', [
			'body' => ['type' => 'url', 'title' => ['en' => 'Evil'], 'path' => ['en' => 'javascript:alert(1)']],
		]);

		$this->assertResponseOk($response);
		$this->assertStringContainsString(
			'URLs must start with',
			$this->getHtmlResponse($response),
		);
		$this->assertSame([], $this->order());
	}

	public function testMovesUpAndDown(): void
	{
		$this->createItem('move-a', position: 1);
		$this->createItem('move-b', position: 2);
		$this->createItem('move-c', position: 3);

		$up = $this->makeRequest('POST', '/cp/menus/tree-menu/item/move-c/move', [
			'body' => ['direction' => 'up'],
		]);
		$this->assertResponseStatus(303, $up);
		$this->assertSame(['move-a', 'move-c', 'move-b'], $this->order());

		$down = $this->makeRequest('POST', '/cp/menus/tree-menu/item/move-a/move', [
			'body' => ['direction' => 'down'],
		]);
		$this->assertResponseStatus(303, $down);
		$this->assertSame(['move-c', 'move-a', 'move-b'], $this->order());
	}

	public function testMoveWithIndexReparents(): void
	{
		$this->createItem('drag-parent', position: 1);
		$this->createItem('drag-x', 'drag-parent', 1);
		$this->createItem('drag-y', 'drag-parent', 2);
		$this->createItem('drag-moved', position: 2);

		$response = $this->makeRequest('POST', '/cp/menus/tree-menu/item/drag-moved/move', [
			'body' => ['parent' => 'drag-parent', 'index' => 1],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertSame(['drag-x', 'drag-moved', 'drag-y'], $this->order('drag-parent'));
		$this->assertSame(['drag-parent'], $this->order());
	}

	public function testMoveIntoTheOwnSubtreeIsRejected(): void
	{
		$this->createItem('cycle-parent');
		$this->createItem('cycle-child', 'cycle-parent');

		$response = $this->makeRequest('POST', '/cp/menus/tree-menu/item/cycle-parent/move', [
			'body' => ['parent' => 'cycle-child', 'index' => 0],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=move-rejected',
			$response->getHeaderLine('Location'),
		);
		$this->assertSame(['cycle-child'], $this->order('cycle-parent'));
	}

	public function testIndentMakesTheItemAChildOfTheSiblingAboveIt(): void
	{
		$this->createItem('ind-first', null, 1);
		$this->createItem('ind-child', 'ind-first', 1);
		$this->createItem('ind-second', null, 2);

		$response = $this->makeRequest('POST', '/cp/menus/tree-menu/item/ind-second/move', [
			'body' => ['direction' => 'in'],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertSame(['ind-first'], $this->order());
		// Appended after the children the new parent already had.
		$this->assertSame(['ind-child', 'ind-second'], $this->order('ind-first'));
	}

	public function testOutdentLiftsTheItemNextToItsFormerParent(): void
	{
		$this->createItem('out-parent', null, 1);
		$this->createItem('out-child', 'out-parent', 1);
		$this->createItem('out-after', null, 2);

		$response = $this->makeRequest('POST', '/cp/menus/tree-menu/item/out-child/move', [
			'body' => ['direction' => 'out'],
		]);

		$this->assertResponseStatus(303, $response);
		// Right after the parent it came from, ahead of what followed it.
		$this->assertSame(['out-parent', 'out-child', 'out-after'], $this->order());
		$this->assertSame([], $this->order('out-parent'));
	}

	public function testTheTreeRendersAsAnAriaTree(): void
	{
		$this->createItem('aria-parent', null, 1);
		$this->createItem('aria-child', 'aria-parent', 1);

		$html = $this->getHtmlResponse($this->makeRequest('GET', '/cp/menus/tree-menu'));

		$this->assertHtmlNodeExists('//*[@role="tree" and @data-menu-tree="tree-menu"]', $html);
		$this->assertHtmlNodeExists('//li[@role="treeitem"]/*[@role="group"]', $html);
		// A row with children announces its state and its depth.
		$this->assertHtmlNodeExists(
			'//li[@role="treeitem" and @data-uid="aria-parent" and @aria-level="1" and @aria-expanded="true"]',
			$html,
		);
		// A leaf claims no expanded state.
		$this->assertHtmlNodeExists(
			'//li[@role="treeitem" and @data-uid="aria-child" and @aria-level="2" and @tabindex="-1" and not(@aria-expanded)]',
			$html,
		);
		// The card's own controls leave the tab order; the row is the stop.
		$this->assertHtmlNodeExists(
			'//li[@role="treeitem"]//a[contains(concat(" ", normalize-space(@class), " "), " text ") and @tabindex="-1"]',
			$html,
		);
	}

	public function testAMoveOffersToPutTheItemBack(): void
	{
		$this->createItem('undo-parent', null, 1);
		$this->createItem('undo-a', 'undo-parent', 1);
		$this->createItem('undo-b', 'undo-parent', 2);

		$moved = $this->makeRequest('POST', '/cp/menus/tree-menu/item/undo-b/move', [
			'body' => ['direction' => 'out'],
		]);

		$location = $moved->getHeaderLine('Location');
		$this->assertStringContainsString('notice=item-moved', $location);
		$this->assertStringContainsString('undoParent=undo-parent', $location);
		$this->assertStringContainsString('undoIndex=1', $location);
		$this->assertSame(['undo-parent', 'undo-b'], $this->order());

		// The tree renders the offer as a form posting the old position back.
		// Matched on the button's class: the panel's embedded message catalog
		// carries an unrelated "Undo" of its own.
		$html = $this->getHtmlResponse($this->makeRequest('GET', $location));
		$this->assertHtmlNodeExists(
			'//form[.//button[contains(concat(" ", normalize-space(@class), " "), " undo ")]]//input[@name="index" and @value="1"]',
			$html,
		);
		$this->assertStringContainsString('“Item undo-b” moved.', $html);

		$undone = $this->makeRequest('POST', '/cp/menus/tree-menu/item/undo-b/move', [
			'body' => ['parent' => 'undo-parent', 'index' => '1'],
		]);

		$this->assertResponseStatus(303, $undone);
		$this->assertSame(['undo-parent'], $this->order());
		$this->assertSame(['undo-a', 'undo-b'], $this->order('undo-parent'));
	}

	public function testARejectedMoveOffersNoUndo(): void
	{
		$this->createItem('reject-root');

		$response = $this->makeRequest('POST', '/cp/menus/tree-menu/item/reject-root/move', [
			'body' => ['direction' => 'out'],
		]);

		$location = $response->getHeaderLine('Location');
		$this->assertStringContainsString('notice=move-rejected', $location);
		$this->assertStringNotContainsString('undoIndex', $location);
		$this->assertHtmlNodeMissing(
			'//button[contains(concat(" ", normalize-space(@class), " "), " undo ")]',
			$this->getHtmlResponse($this->makeRequest('GET', $location)),
		);
	}

	public function testTheTreeDisablesUndefinedMoves(): void
	{
		$this->createItem('edge-first', null, 1);
		$this->createItem('edge-child', 'edge-first', 1);

		$html = $this->getHtmlResponse($this->makeRequest('GET', '/cp/menus/tree-menu'));

		// A first root item can neither move up nor indent, and has no parent
		// to outdent from; its child can do both but not move among siblings.
		$this->assertHtmlNodeExists(
			'//li[@data-uid="edge-first"]//form[input[@name="direction" and @value="in"]]/button[@type="submit" and @disabled]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//li[@data-uid="edge-first"]//form[input[@name="direction" and @value="out"]]/button[@type="submit" and @disabled]',
			$html,
		);
		$this->assertStringContainsString('Indent', $html);
		$this->assertStringContainsString('Outdent', $html);
	}

	public function testAnUndefinedMoveIsRejectedRatherThanGuessed(): void
	{
		$this->createItem('lone-root');

		foreach (['in', 'out'] as $direction) {
			$response = $this->makeRequest('POST', '/cp/menus/tree-menu/item/lone-root/move', [
				'body' => ['direction' => $direction],
			]);

			$this->assertResponseStatus(303, $response);
			$this->assertStringContainsString(
				'notice=move-rejected',
				$response->getHeaderLine('Location'),
			);
		}

		$this->assertSame(['lone-root'], $this->order());
	}

	public function testIndentingPastTheMaxDepthIsRejected(): void
	{
		$this->db()->execute(
			"UPDATE cms.menus SET max_depth = 1 WHERE menu = 'tree-menu'",
		)->run();
		$this->createItem('flat-first', null, 1);
		$this->createItem('flat-second', null, 2);

		$response = $this->makeRequest('POST', '/cp/menus/tree-menu/item/flat-second/move', [
			'body' => ['direction' => 'in'],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=move-rejected',
			$response->getHeaderLine('Location'),
		);
		$this->assertSame(['flat-first', 'flat-second'], $this->order());
	}

	public function testHidingAnItemKeepsItInTheEditorAndOutOfThePreview(): void
	{
		$this->createItem('vis-item');

		$response = $this->makeRequest('POST', '/cp/menus/tree-menu/item/vis-item', [
			'body' => [
				'type' => 'url',
				'title' => ['en' => 'Seasonal'],
				'path' => ['en' => '/seasonal'],
				'hidden' => '1',
			],
		]);
		$this->assertResponseStatus(303, $response);

		$html = $this->getHtmlResponse(
			$this->makeRequest('GET', '/cp/menus/tree-menu?item=vis-item'),
		);
		// The tree still shows it, marked; the preview below renders the menu
		// as the frontend would and must not.
		$this->assertHtmlNodeExists(
			'//li[@role="treeitem" and @data-uid="vis-item"]/div[contains(concat(" ", normalize-space(@class), " "), " is-hidden ")]',
			$html,
		);
		$this->assertHtmlNodeMissing('//a[@href="/seasonal"]', $html);
		$this->assertHtmlNodeExists(
			'//form[@action="/cp/menus/tree-menu/item/vis-item"]//input[@name="hidden" and @checked]',
			$html,
		);

		$shown = $this->makeRequest('POST', '/cp/menus/tree-menu/item/vis-item', [
			'body' => [
				'type' => 'url',
				'title' => ['en' => 'Seasonal'],
				'path' => ['en' => '/seasonal'],
			],
		]);
		$this->assertResponseStatus(303, $shown);
		$this->assertStringContainsString(
			'href="/seasonal"',
			$this->getHtmlResponse($this->makeRequest('GET', '/cp/menus/tree-menu')),
		);
	}

	public function testAMovePastTheMaxDepthIsRejected(): void
	{
		$this->db()->execute(
			"UPDATE cms.menus SET max_depth = 2 WHERE menu = 'tree-menu'",
		)->run();
		$this->createItem('depth-host');
		$this->createItem('depth-branch');
		$this->createItem('depth-leaf', 'depth-branch');

		// The branch would land on level 2, but its leaf on level 3.
		$response = $this->makeRequest('POST', '/cp/menus/tree-menu/item/depth-branch/move', [
			'body' => ['parent' => 'depth-host', 'index' => 0],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=move-rejected',
			$response->getHeaderLine('Location'),
		);
		$this->assertSame([], $this->order('depth-host'));
	}

	public function testDeleteRemovesTheSubtree(): void
	{
		$this->createItem('doom-root');
		$this->createItem('doom-child', 'doom-root');
		$this->createItem('doom-grandchild', 'doom-child');
		$this->createItem('doom-other', position: 2);

		$response = $this->makeRequest('POST', '/cp/menus/tree-menu/item/doom-root/delete');

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=item-deleted',
			$response->getHeaderLine('Location'),
		);
		$this->assertSame(['doom-other'], $this->order());
	}

	public function testItemsFromAnotherMenuAnswer404(): void
	{
		$this->db()->execute(
			"INSERT INTO cms.menus (menu, description) VALUES ('other-menu', '{\"zxx\": \"Other\"}')",
		)->run();
		$this->db()->execute(
			"INSERT INTO cms.menu_items (item, parent, menu, position, data)
			VALUES ('foreign-item', NULL, 'other-menu', 1, '{\"type\": \"label\", \"title\": {\"en\": \"X\"}}'::jsonb)",
		)->run();

		$this->assertResponseStatus(
			404,
			$this->makeRequest('GET', '/cp/menus/tree-menu?item=foreign-item'),
		);
		$this->assertResponseStatus(
			404,
			$this->makeRequest('POST', '/cp/menus/tree-menu/item/foreign-item/delete'),
		);
		$this->assertResponseStatus(
			404,
			$this->makeRequest('POST', '/cp/menus/tree-menu/item/foreign-item/move', [
				'body' => ['direction' => 'up'],
			]),
		);
	}

	public function testCreatesAndDisplaysAChildrenItem(): void
	{
		$this->createTestNode([
			'uid' => 'menu-children-root',
			'type' => $this->nodeTypeId,
			'published' => true,
			'content' => ['title' => ['type' => 'text', 'value' => ['en' => 'Products']]],
		]);

		$rejected = $this->makeRequest('POST', '/cp/menus/tree-menu/item/create', [
			'body' => ['type' => 'children', 'node' => 'never-was'],
		]);
		$this->assertResponseOk($rejected);
		$this->assertStringContainsString(
			'Pick an existing page.',
			$this->getHtmlResponse($rejected),
		);

		$accepted = $this->makeRequest('POST', '/cp/menus/tree-menu/item/create', [
			'body' => [
				'type' => 'children',
				'node' => 'menu-children-root',
				'levels' => '2',
				'order' => 'created desc',
				// Hidden sections still submit; the payload must drop them.
				'title' => ['en' => 'Ignored'],
				'class' => 'ignored',
			],
		]);
		$this->assertResponseStatus(303, $accepted);

		$item = $this->order()[0];
		$this->assertEquals(
			[
				'type' => 'children',
				'node' => 'menu-children-root',
				'levels' => 2,
				'order' => 'created desc',
			],
			$this->itemData($item),
		);

		// The editor tree names the source node and stays unexpanded.
		$html = $this->getHtmlResponse($this->makeRequest('GET', '/cp/menus/tree-menu'));
		$this->assertStringContainsString('Children of &quot;Products&quot;', $html);

		// Editing prefills the configuration.
		$pane = $this->getHtmlResponse(
			$this->makeRequest('GET', '/cp/menus/tree-menu?item=' . $item),
		);
		$this->assertHtmlNodeExists('//input[@name="levels" and @value="2"]', $pane);
		$this->assertHtmlNodeExists('//option[@value="created desc" and @selected]', $pane);
	}

	public function testLegacyNodeStubKeepsItsSnapshotInTheTree(): void
	{
		$this->createItem('legacy-stub', null, 1, [
			'type' => 'node',
			'node' => 0,
			'title' => ['en' => 'Legacy Snapshot'],
			'path' => ['en' => '/legacy-path'],
		]);

		$html = $this->getHtmlResponse($this->makeRequest('GET', '/cp/menus/tree-menu'));

		$this->assertStringContainsString('Legacy Snapshot', $html);
		$this->assertStringContainsString('/legacy-path', $html);
	}

	public function testPreviewRendersTheMenuAsTheFrontendWould(): void
	{
		$this->createItem('preview-item', null, 1, [
			'type' => 'url',
			'title' => ['en' => 'Preview Me'],
			'path' => ['en' => '/preview-me'],
		]);

		$html = $this->getHtmlResponse($this->makeRequest('GET', '/cp/menus/tree-menu'));

		$this->assertStringContainsString('<details class="preview">', $html);
		$this->assertStringContainsString('hx-boost="false"', $html);
		$this->assertStringContainsString('<ul class="nav-level-1">', $html);
		$this->assertStringContainsString('<a href="/preview-me">', $html);
	}

	public function testEditorsAreForbidden(): void
	{
		$this->authenticateAs('editor');

		$this->assertResponseStatus(403, $this->makeRequest('GET', '/cp/menus/tree-menu'));
		$this->assertResponseStatus(403, $this->makeRequest('POST', '/cp/menus/tree-menu/item/create', [
			'body' => ['type' => 'label', 'title' => ['en' => 'Nope']],
		]));
	}
}
