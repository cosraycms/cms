<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Actor;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Field\Services;
use Cosray\Finder\Nodes;
use Cosray\Finder\Order;
use Cosray\Finder\SortField;
use Cosray\Locales;
use Cosray\Node\PathManager;
use Cosray\Node\Positions;
use Cosray\Node\ReservedPaths;
use Cosray\Node\Store;
use Cosray\Node\Wrapper;
use Cosray\Node\Writer;
use Cosray\Tests\Fixtures\Node\PlainBlock;
use Cosray\Tests\IntegrationTestCase;

final class NodePositionsTest extends IntegrationTestCase
{
	private Positions $positions;
	private int $page;
	private int $sortable;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
		$this->positions = new Positions($this->db());
		$this->page = $this->createTestType('ordered-test-page');
		$this->sortable = $this->createTestType('sortable-test-parent');
	}

	public function testChildrenFollowTheManualOrderOfASortableParent(): void
	{
		$parent = $this->parent('arranged');
		$this->child($parent, 'c-a', 'Charlie');
		$this->child($parent, 'c-b', 'Alpha');
		$this->child($parent, 'c-c', 'Bravo');
		$cms = $this->createCms();

		// Nobody arranged them yet: the title order they had before.
		$this->assertSame(['c-b', 'c-c', 'c-a'], $this->uids($this->children($cms, 'arranged')));

		$this->positions->orderChildren('arranged', ['c-a', 'c-c', 'c-b']);
		$this->child($parent, 'c-d', 'Aardvark');

		// A newcomer follows the arranged ones.
		$this->assertSame(['c-a', 'c-c', 'c-b', 'c-d'], $this->uids($this->children($cms, 'arranged')));
		$this->assertSame(
			['c-d', 'c-c', 'c-b', 'c-a'],
			$this->uids($this->children($cms, 'arranged')->order('uid DESC')),
			'An explicit order replaces the manual one',
		);
	}

	public function testReorderingReplacesTheWholeScope(): void
	{
		$parent = $this->parent('replaced');
		$this->child($parent, 'r-a', 'Alpha');
		$this->child($parent, 'r-b', 'Bravo');
		$this->child($parent, 'r-c', 'Charlie');
		$other = $this->parent('elsewhere');
		$this->child($other, 'r-x', 'Aardvark');

		$this->positions->orderChildren('replaced', ['r-c', 'r-b', 'r-a']);
		// r-b drops out of the arranged order, r-x is not a child here.
		$this->positions->orderChildren('replaced', ['r-a', 'r-x', 'r-c']);

		$this->assertSame(['r-a', 'r-c', 'r-b'], $this->uids($this->children($this->createCms(), 'replaced')));
		$this->assertSame([['node' => 'r-a', 'position' => 1], ['node' => 'r-c', 'position' => 3]], $this->rows());
	}

	public function testCollectionOrdersAreIndependentOfEachOtherAndOfParents(): void
	{
		$parent = $this->parent('shared-parent');
		$this->child($parent, 's-a', 'Alpha');
		$this->child($parent, 's-b', 'Bravo');
		$this->child($parent, 's-c', 'Charlie');

		$this->positions->orderChildren('shared-parent', ['s-b', 's-a', 's-c']);
		$this->positions->orderEntries('first', ['s-c', 's-a', 's-b']);
		$this->positions->orderEntries('second', ['s-a', 's-c']);
		$cms = $this->createCms();
		$entries = static fn(string $collection, string $direction = 'asc'): Nodes => $cms
			->nodes()
			->only('s-a', 's-b', 's-c')
			->order(new Order(SortField::position($collection), $direction));

		$this->assertSame(['s-c', 's-a', 's-b'], $this->uids($entries('first')));
		$this->assertSame(['s-b', 's-a', 's-c'], $this->uids($entries('first', 'desc')));
		$this->assertSame(['s-a', 's-c', 's-b'], $this->uids($entries('second')));
		$this->assertSame(['s-b', 's-a', 's-c'], $this->uids($this->children($cms, 'shared-parent')));
		$this->assertSame(
			['s-a', 's-b', 's-c'],
			$this->uids(
				$cms
					->nodes()
					->only('s-a', 's-b', 's-c')
					->order(new Order(SortField::position('none'))),
			),
			'A collection nobody arranged keeps the title order',
		);
	}

	public function testMovingToAnotherParentDropsTheFormerPlace(): void
	{
		$locales = new Locales();
		$locales->add('en', title: 'English');
		$context = Context::console($this->db(), $this->config(), $this->container(), $this->factory(), $locales);
		$services = Services::withDefaults();
		$cms = new Cms($context, $services);
		$factory = $cms->nodeFactory();
		$writer = new Writer($context, $cms, $services->types);
		$store = new Store(
			$this->db(),
			new PathManager(ReservedPaths::fromConfig($context->config)),
			$services->types,
			$factory->uid(),
			factory: $factory,
			cms: $cms,
			context: $context,
		);
		$writer->create($writer->prepare(PlainBlock::class)->uid('move-from'));
		$writer->create($writer->prepare(PlainBlock::class)->uid('move-to'));
		$writer->create($writer->prepare(PlainBlock::class)->uid('move-child')->parent('move-from'));
		$writer->create($writer->prepare(PlainBlock::class)->uid('move-stays')->parent('move-to'));
		$this->positions->orderChildren('move-from', ['move-child']);
		$this->positions->orderChildren('move-to', ['move-stays']);

		foreach (['move-stays' => 'move-to', 'move-child' => 'move-to'] as $uid => $parent) {
			$row = $this->db()->execute('SELECT * FROM cms.nodes WHERE uid = :uid', ['uid' => $uid])->one();
			$node = $factory->create(PlainBlock::class, $context, $cms, [
				...$row,
				'content' => json_decode($row['content'], true),
			]);
			$data = $writer->prepare(PlainBlock::class)->uid($uid)->parent($parent)->data();
			$store->save($node, $data, $context->locales(), Actor::system());
		}

		// Saving under the same parent keeps the place, moving away drops it.
		$this->assertSame([['node' => 'move-stays', 'position' => 1]], $this->rows());
	}

	private function parent(string $uid): int
	{
		return $this->createTestNode([
			'uid' => $uid,
			'type' => $this->sortable,
		]);
	}

	private function child(int $parent, string $uid, string $title): void
	{
		$this->createTestNode([
			'uid' => $uid,
			'parent' => $parent,
			'type' => $this->page,
			'title' => ['zxx' => $title],
		]);
	}

	private function children(Cms $cms, string $uid): Nodes
	{
		$node = $cms->node->byUid($uid, published: null);
		$this->assertInstanceOf(Wrapper::class, $node);

		return $node->children();
	}

	/** @return list<string> */
	private function uids(Nodes $nodes): array
	{
		return array_map(static fn(Wrapper $node): string => $node->meta->uid, iterator_to_array($nodes));
	}

	/** @return list<array{node: string, position: int}> */
	private function rows(): array
	{
		return $this->db()->execute(
			'SELECT n.uid AS node, p.position FROM cms.node_positions p
			INNER JOIN cms.nodes n ON n.node = p.node
			ORDER BY p.position',
		)->all();
	}
}
