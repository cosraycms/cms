<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Tests\IntegrationTestCase;

/**
 * Tests for complex Finder queries with pagination, combined filters, and search.
 *
 * @internal
 *
 * @coversNothing
 */
final class ComplexFinderQueriesTest extends IntegrationTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types', 'sample-nodes');
	}

	public function testPaginationWithOffsetAndLimit(): void
	{
		$typeId = $this->createTestType('pagination-test');

		// Create 10 nodes
		for ($i = 1; $i <= 10; $i++) {
			$this->createTestNode([
				'uid' => "pagination-node-{$i}",
				'type' => $typeId,
				'published' => true,
			]);
		}

		// Get first 3
		$firstPage = $this->db()->execute(
			'SELECT uid FROM cms.nodes WHERE type = :type AND published = true ORDER BY node LIMIT 3',
			['type' => $typeId],
		)->all();

		$this->assertCount(3, $firstPage);
		$this->assertEquals('pagination-node-1', $firstPage[0]['uid']);
		$this->assertEquals('pagination-node-2', $firstPage[1]['uid']);
		$this->assertEquals('pagination-node-3', $firstPage[2]['uid']);

		// Get next 3 (offset 3)
		$secondPage = $this->db()->execute(
			'SELECT uid FROM cms.nodes WHERE type = :type AND published = true ORDER BY node LIMIT 3 OFFSET 3',
			['type' => $typeId],
		)->all();

		$this->assertCount(3, $secondPage);
		$this->assertEquals('pagination-node-4', $secondPage[0]['uid']);
		$this->assertEquals('pagination-node-5', $secondPage[1]['uid']);
		$this->assertEquals('pagination-node-6', $secondPage[2]['uid']);
	}

	public function testCombinedFiltersPublishedAndHidden(): void
	{
		$typeId = $this->createTestType('combined-filter-test');

		// Create nodes with different published/hidden combinations
		$this->createTestNode([
			'uid' => 'pub-visible',
			'type' => $typeId,
			'published' => true,
			'hidden' => false,
		]);
		$this->createTestNode([
			'uid' => 'pub-hidden',
			'type' => $typeId,
			'published' => true,
			'hidden' => true,
		]);
		$this->createTestNode([
			'uid' => 'unpub-visible',
			'type' => $typeId,
			'published' => false,
			'hidden' => false,
		]);
		$this->createTestNode([
			'uid' => 'unpub-hidden',
			'type' => $typeId,
			'published' => false,
			'hidden' => true,
		]);

		// Get published and not hidden (visible to public)
		$visible = $this->db()->execute(
			'SELECT uid FROM cms.nodes WHERE type = :type AND published = true AND hidden = false',
			['type' => $typeId],
		)->all();

		$this->assertCount(1, $visible);
		$this->assertEquals('pub-visible', $visible[0]['uid']);

		// Get published (including hidden)
		$published = $this->db()->execute(
			'SELECT uid FROM cms.nodes WHERE type = :type AND published = true',
			['type' => $typeId],
		)->all();

		$this->assertCount(2, $published);
	}

	public function testQueryByJsonbContentValue(): void
	{
		$typeId = $this->createTestType('jsonb-query-test');

		// Create nodes with different content values
		$this->createTestNode([
			'uid' => 'status-draft',
			'type' => $typeId,
			'content' => ['status' => ['type' => 'option', 'value' => 'draft']],
		]);

		$this->createTestNode([
			'uid' => 'status-published',
			'type' => $typeId,
			'content' => ['status' => ['type' => 'option', 'value' => 'published']],
		]);

		$this->createTestNode([
			'uid' => 'status-archived',
			'type' => $typeId,
			'content' => ['status' => ['type' => 'option', 'value' => 'archived']],
		]);

		// Query for draft status
		$drafts = $this->db()->execute(
			"SELECT uid FROM cms.nodes
			 WHERE type = :type
			 AND content->'status'->'value'->>'zxx' = 'draft'",
			['type' => $typeId],
		)->all();

		$this->assertCount(1, $drafts);
		$this->assertEquals('status-draft', $drafts[0]['uid']);
	}

	public function testQueryByJsonbContentWithLocale(): void
	{
		$typeId = $this->createTestType('jsonb-locale-query-test');

		$this->createTestNode([
			'uid' => 'en-only-node',
			'type' => $typeId,
			'content' => [
				'title' => [
					'type' => 'text',
					'value' => ['en' => 'English Only', 'de' => null],
				],
			],
		]);

		$this->createTestNode([
			'uid' => 'de-only-node',
			'type' => $typeId,
			'content' => [
				'title' => [
					'type' => 'text',
					'value' => ['en' => null, 'de' => 'Nur Deutsch'],
				],
			],
		]);

		// Query for nodes with German content
		$germanNodes = $this->db()->execute(
			"SELECT uid FROM cms.nodes
			 WHERE type = :type
			 AND content->'title'->'value'->>'de' IS NOT NULL",
			['type' => $typeId],
		)->all();

		$this->assertCount(1, $germanNodes);
		$this->assertEquals('de-only-node', $germanNodes[0]['uid']);
	}

	public function testOrderingByMultipleCriteria(): void
	{
		$typeId = $this->createTestType('multi-order-test');

		// Create nodes with different published status and dates
		$this->db()->execute(
			"INSERT INTO cms.nodes (uid, type, published, hidden, creator, editor, created, content)
			 VALUES ('order-1', :type, true, false, 1, 1, '2024-01-15', '{}'::jsonb)",
			['type' => $typeId],
		)->run();

		$this->db()->execute(
			"INSERT INTO cms.nodes (uid, type, published, hidden, creator, editor, created, content)
			 VALUES ('order-2', :type, true, false, 1, 1, '2024-01-10', '{}'::jsonb)",
			['type' => $typeId],
		)->run();

		$this->db()->execute(
			"INSERT INTO cms.nodes (uid, type, published, hidden, creator, editor, created, content)
			 VALUES ('order-3', :type, false, false, 1, 1, '2024-01-20', '{}'::jsonb)",
			['type' => $typeId],
		)->run();

		// Order by published DESC, created ASC
		$ordered = $this->db()->execute(
			'SELECT uid FROM cms.nodes WHERE type = :type ORDER BY published DESC, created ASC',
			['type' => $typeId],
		)->all();

		// Published (true) comes first, then by date
		// Within published: order-2 (Jan 10), order-1 (Jan 15)
		// Then unpublished: order-3
		$this->assertEquals('order-2', $ordered[0]['uid']); // Published, earliest date
		$this->assertEquals('order-1', $ordered[1]['uid']); // Published, later date
		$this->assertEquals('order-3', $ordered[2]['uid']); // Unpublished
	}

	public function testCountWithFilters(): void
	{
		$typeId = $this->createTestType('count-filter-test');

		// Create 5 published and 3 unpublished nodes
		for ($i = 1; $i <= 5; $i++) {
			$this->createTestNode([
				'uid' => "count-pub-{$i}",
				'type' => $typeId,
				'published' => true,
			]);
		}

		for ($i = 1; $i <= 3; $i++) {
			$this->createTestNode([
				'uid' => "count-unpub-{$i}",
				'type' => $typeId,
				'published' => false,
			]);
		}

		// Count all
		$total = $this->db()->execute(
			'SELECT COUNT(*) as count FROM cms.nodes WHERE type = :type',
			['type' => $typeId],
		)->one()['count'];
		$this->assertEquals(8, $total);

		// Count published
		$published = $this->db()->execute(
			'SELECT COUNT(*) as count FROM cms.nodes WHERE type = :type AND published = true',
			['type' => $typeId],
		)->one()['count'];
		$this->assertEquals(5, $published);

		// Count unpublished
		$unpublished = $this->db()->execute(
			'SELECT COUNT(*) as count FROM cms.nodes WHERE type = :type AND published = false',
			['type' => $typeId],
		)->one()['count'];
		$this->assertEquals(3, $unpublished);
	}

	public function testQueryWithJsonbArrayContains(): void
	{
		$typeId = $this->createTestType('jsonb-array-test');

		// Node with multiple categories
		$this->createTestNode([
			'uid' => 'multi-cat-node',
			'type' => $typeId,
			'content' => [
				'categories' => [
					'type' => 'option',
					'value' => ['news', 'featured', 'technology'],
				],
			],
		]);

		// Node with different categories
		$this->createTestNode([
			'uid' => 'single-cat-node',
			'type' => $typeId,
			'content' => [
				'categories' => [
					'type' => 'option',
					'value' => ['news'],
				],
			],
		]);

		// Query nodes that have 'news' in categories using JSONB containment
		$newsNodes = $this->db()->execute(
			"SELECT uid FROM cms.nodes
			 WHERE type = :type
			 AND content->'categories'->'value'->'zxx' @> '[\"news\"]'::jsonb",
			['type' => $typeId],
		)->all();

		$this->assertCount(2, $newsNodes);

		// Query nodes with 'featured'
		$featuredNodes = $this->db()->execute(
			"SELECT uid FROM cms.nodes
			 WHERE type = :type
			 AND content->'categories'->'value'->'zxx' @> '[\"featured\"]'::jsonb",
			['type' => $typeId],
		)->all();

		$this->assertCount(1, $featuredNodes);
		$this->assertEquals('multi-cat-node', $featuredNodes[0]['uid']);
	}

	public function testComplexQueryWithJoins(): void
	{
		$typeId = $this->createTestType('complex-join-test');

		$nodeId = $this->createTestNode([
			'uid' => 'path-node-1',
			'type' => $typeId,
			'published' => true,
		]);

		$this->createTestPath($nodeId, '/test-path', 'en');

		// Query nodes with their paths using JOIN
		$nodesWithPaths = $this->db()->execute(
			'SELECT n.uid, u.path, u.locale
			 FROM cms.nodes n
			 LEFT JOIN cms.url_paths u ON n.node = u.node
			 WHERE n.type = :type AND n.published = true',
			['type' => $typeId],
		)->all();

		$this->assertCount(1, $nodesWithPaths);
		$this->assertEquals('path-node-1', $nodesWithPaths[0]['uid']);
		$this->assertEquals('/test-path', $nodesWithPaths[0]['path']);
		$this->assertEquals('en', $nodesWithPaths[0]['locale']);
	}

	public function testDateRangeQuery(): void
	{
		$typeId = $this->createTestType('date-range-test');

		// Insert nodes with specific dates
		$this->db()->execute(
			"INSERT INTO cms.nodes (uid, type, creator, editor, created, content)
			 VALUES ('old-node', :type, 1, 1, '2023-01-01', '{}'::jsonb)",
			['type' => $typeId],
		)->run();

		$this->db()->execute(
			"INSERT INTO cms.nodes (uid, type, creator, editor, created, content)
			 VALUES ('recent-node', :type, 1, 1, '2024-06-15', '{}'::jsonb)",
			['type' => $typeId],
		)->run();

		// Query nodes from 2024
		$recentNodes = $this->db()->execute(
			"SELECT uid FROM cms.nodes
			 WHERE type = :type
			 AND created >= '2024-01-01'
			 AND created < '2025-01-01'",
			['type' => $typeId],
		)->all();

		$this->assertCount(1, $recentNodes);
		$this->assertEquals('recent-node', $recentNodes[0]['uid']);
	}
}
