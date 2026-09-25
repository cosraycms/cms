<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Collection\Listing;
use Cosray\Collection\Schemas;
use Cosray\Context;
use Cosray\Contract\Entries;
use Cosray\Field\Services;
use Cosray\Finder\Nodes;
use Cosray\Node\Types;
use Cosray\Schema\Types as TypesAttribute;
use Cosray\Tests\Fixtures\Collection\TestSortedCollection;
use Cosray\Tests\Fixtures\Node\TestSortableEntry;
use Cosray\Tests\IntegrationTestCase;

final class CollectionListingTest extends IntegrationTestCase
{
	public function testCompoundSortsReplaceQueryOrderAndKeepPaginationStable(): void
	{
		$type = $this->createTestType('test-sortable-entry');
		foreach ([
			'a' => ['Smith', 'Zoe'],
			'b' => ['Smith', 'Amy'],
			'c' => ['Smith', 'Amy'],
			'd' => ['Brown', 'Zoe'],
		] as $uid => [$last, $first]) {
			$this->createTestNode([
				'uid' => $uid,
				'type' => $type,
				'content' => [
					'lastName' => ['type' => 'text', 'value' => ['zxx' => $last]],
					'firstName' => ['type' => 'text', 'value' => ['zxx' => $first]],
				],
			]);
		}
		$cms = $this->cms();
		$collection = new TestSortedCollection();
		$listing = new Listing(new Schemas()->of($collection::class), $cms, new Types(), $collection);
		$default = $listing->list();
		$this->assertSame('name', $default['sort']);
		$this->assertSame('asc', $default['dir']);
		$this->assertSame(['d', 'b', 'c', 'a'], array_column($default['nodes'], 'uid'));

		$actual = [];
		for ($offset = 0; $offset < 4; $offset++) {
			$page = $listing->list(offset: $offset, limit: 1, dir: 'desc');
			$this->assertSame(4, $page['total']);
			$actual[] = $page['nodes'][0]['uid'];
		}
		$this->assertSame(['a', 'b', 'c', 'd'], $actual);
		$this->assertSame('desc', $listing->list(sort: 'created')['dir']);
		$this->assertSame(1, $collection->columnsCalls);
	}

	public function testEntriesCoverDeclaredTypesInAnyStateAndEntriesNarrowThem(): void
	{
		$type = $this->createTestType('test-sortable-entry');
		$other = $this->createTestType('test-other-entry');
		foreach ([
			'live' => [$type, true, false],
			'draft' => [$type, false, false],
			'hidden' => [$type, true, true],
			'other' => [$other, true, false],
		] as $uid => [$typeId, $published, $hidden]) {
			$this->createTestNode(['uid' => $uid, 'type' => $typeId, 'published' => $published, 'hidden' => $hidden]);
		}
		$cms = $this->cms();
		$schemas = new Schemas();

		$declared = new Listing($schemas->of(SortableEntries::class), $cms, new Types());
		$this->assertSame(['draft', 'hidden', 'live'], $this->uids($declared));

		$narrowed = new Listing(
			$schemas->of(PublishedSortableEntries::class),
			$cms,
			new Types(),
			new PublishedSortableEntries(),
		);
		$this->assertSame(['hidden', 'live'], $this->uids($narrowed));
	}

	private function cms(): Cms
	{
		$container = $this->container();
		$container->tag(Bootstrap::NODE_TAG)->add('test-sortable-entry', TestSortableEntry::class);

		return new Cms(
			new Context($this->db(), $this->request(), $this->config(), $container, $this->factory()),
			Services::withDefaults(),
		);
	}

	/** @return list<string> */
	private function uids(Listing $listing): array
	{
		$uids = array_column($listing->list()['nodes'], 'uid');
		sort($uids);

		return $uids;
	}
}

#[TypesAttribute('test-sortable-entry')]
final class SortableEntries {}

#[TypesAttribute('test-sortable-entry')]
final class PublishedSortableEntries implements Entries
{
	public function entries(Nodes $nodes): Nodes
	{
		return $nodes->published(true);
	}
}
