<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Collection\Listing;
use Cosray\Context;
use Cosray\Field\Services;
use Cosray\Node\Types;
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
		$container = $this->container();
		$container->tag(Bootstrap::NODE_TAG)->add('test-sortable-entry', TestSortableEntry::class);
		$cms = new Cms(
			new Context($this->db(), $this->request(), $this->config(), $container, $this->factory()),
			Services::withDefaults(),
		);
		$collection = new TestSortedCollection($cms);
		$listing = new Listing($collection, $cms, new Types());
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
}
