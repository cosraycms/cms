<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\End2End;

use Celemas\Cms\Config;
use Celemas\Cms\Plugin;
use Celemas\Cms\Tests\End2EndTestCase;
use Celemas\Cms\Tests\Fixtures\Collection\TestHierarchyCollection;
use Celemas\Cms\Tests\Fixtures\Node\TestHierarchyChild;
use Celemas\Cms\Tests\Fixtures\Node\TestHierarchyParent;

final class CollectionHierarchyTest extends End2EndTestCase
{
	private int $parentTypeId;
	private int $childTypeId;

	protected function setUp(): void
	{
		parent::setUp();

		$this->authenticateAs('editor');
		$this->parentTypeId = $this->createTestType('test-hierarchy-parent');
		$this->childTypeId = $this->createTestType('test-hierarchy-child');
	}

	protected function createPlugin(Config $config): Plugin
	{
		$plugin = parent::createPlugin($config);
		$plugin->node(TestHierarchyParent::class);
		$plugin->node(TestHierarchyChild::class);
		$plugin->collection(TestHierarchyCollection::class);

		return $plugin;
	}

	public function testHierarchyCollectionReturnsRootsWithMetadata(): void
	{
		$rootId = $this->createTestNode([
			'uid' => 'hierarchy-root',
			'type' => $this->parentTypeId,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Hierarchy Root']],
			],
		]);
		$this->createTestNode([
			'uid' => 'hierarchy-secondary-root',
			'type' => $this->childTypeId,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Secondary Root']],
			],
		]);
		$this->createTestNode([
			'uid' => 'hierarchy-child',
			'type' => $this->childTypeId,
			'parent' => $rootId,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Hierarchy Child']],
			],
		]);

		$response = $this->makeRequest('GET', '/api/collection/test-hierarchy', [
			'query' => [
				'limit' => '50',
				'offset' => '0',
			],
		]);

		$payload = $this->assertJsonResponse($response, 200);

		$this->assertTrue($payload['showChildren']);
		$this->assertCount(2, $payload['nodes']);

		$rootRow = $this->findRow($payload['nodes'], 'hierarchy-root');
		$this->assertNotNull($rootRow);
		$this->assertNull($rootRow['parent']);
		$this->assertTrue($rootRow['hasChildren']);
		$this->assertSame(
			[
				[
					'slug' => 'test-hierarchy-parent',
					'name' => 'Hierarchy Parent',
				],
				[
					'slug' => 'test-hierarchy-child',
					'name' => 'Hierarchy Child',
				],
			],
			$rootRow['childBlueprints'],
		);

		$secondaryRoot = $this->findRow($payload['nodes'], 'hierarchy-secondary-root');
		$this->assertNotNull($secondaryRoot);
		$this->assertFalse($secondaryRoot['hasChildren']);
		$this->assertSame([], $secondaryRoot['childBlueprints']);
	}

	public function testHierarchyCollectionLoadsDirectChildrenByParentUid(): void
	{
		$rootId = $this->createTestNode([
			'uid' => 'hierarchy-parent-filter',
			'type' => $this->parentTypeId,
		]);
		$childParentId = $this->createTestNode([
			'uid' => 'hierarchy-direct-a',
			'type' => $this->childTypeId,
			'parent' => $rootId,
		]);
		$this->createTestNode([
			'uid' => 'hierarchy-direct-b',
			'type' => $this->childTypeId,
			'parent' => $rootId,
		]);
		$this->createTestNode([
			'uid' => 'hierarchy-grandchild',
			'type' => $this->childTypeId,
			'parent' => $childParentId,
		]);

		$response = $this->makeRequest('GET', '/api/collection/test-hierarchy', [
			'query' => [
				'parent' => 'hierarchy-parent-filter',
			],
		]);

		$payload = $this->assertJsonResponse($response, 200);
		$this->assertCount(2, $payload['nodes']);

		$uids = array_column($payload['nodes'], 'uid');
		sort($uids);
		$this->assertSame(['hierarchy-direct-a', 'hierarchy-direct-b'], $uids);

		$directA = $this->findRow($payload['nodes'], 'hierarchy-direct-a');
		$this->assertNotNull($directA);
		$this->assertSame('hierarchy-parent-filter', $directA['parent']);
		$this->assertTrue($directA['hasChildren']);
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 */
	private function findRow(array $rows, string $uid): ?array
	{
		foreach ($rows as $row) {
			if (($row['uid'] ?? null) === $uid) {
				return $row;
			}
		}

		return null;
	}
}
