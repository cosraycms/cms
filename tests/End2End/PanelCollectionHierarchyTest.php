<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Config;
use Cosray\Plugin;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestHierarchyCollection;
use Cosray\Tests\Fixtures\Node\TestHierarchyChild;
use Cosray\Tests\Fixtures\Node\TestHierarchyParent;

final class PanelCollectionHierarchyTest extends End2EndTestCase
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

	public function testHierarchyCollectionRendersRootRowsWithTreeControls(): void
	{
		$rootId = $this->createHierarchyNode(
			uid: 'panel-hierarchy-root',
			type: $this->parentTypeId,
			title: 'Panel Hierarchy Root',
		);
		$this->createHierarchyNode(
			uid: 'panel-hierarchy-child',
			type: $this->childTypeId,
			title: 'Panel Hierarchy Child',
			parent: $rootId,
		);

		$response = $this->makeRequest('GET', '/cp/collection/test-hierarchy');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('Panel Hierarchy Root', $html);
		$this->assertStringNotContainsString('Panel Hierarchy Child', $html);
		$this->assertStringNotContainsString('class="col-children"', $html);
		$this->assertStringContainsString(
			'aria-label="Expand children of Panel Hierarchy Root"',
			$html,
		);
		$this->assertStringContainsString(
			'href="/cp/collection/test-hierarchy?sort=changed&amp;dir=desc&amp;open=panel-hierarchy-root"',
			$html,
		);
		$this->assertStringContainsString(
			'href="/cp/collection/test-hierarchy?sort=changed&amp;dir=desc&amp;parent=panel-hierarchy-root"',
			$html,
		);
	}

	public function testHierarchyTreeRendersOpenedDirectChildren(): void
	{
		$rootId = $this->createHierarchyNode(
			uid: 'panel-tree-root',
			type: $this->parentTypeId,
			title: 'Panel Tree Root',
		);
		$childId = $this->createHierarchyNode(
			uid: 'panel-tree-child',
			type: $this->parentTypeId,
			title: 'Panel Tree Child',
			parent: $rootId,
		);
		$this->createHierarchyNode(
			uid: 'panel-tree-grandchild',
			type: $this->childTypeId,
			title: 'Panel Tree Grandchild',
			parent: $childId,
		);

		$response = $this->makeRequest('GET', '/cp/collection/test-hierarchy', [
			'query' => [
				'open' => 'panel-tree-root',
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('Panel Tree Root', $html);
		$this->assertStringContainsString('Panel Tree Child', $html);
		$this->assertStringNotContainsString('Panel Tree Grandchild', $html);
		$this->assertStringContainsString(
			'aria-label="Collapse children of Panel Tree Root"',
			$html,
		);
	}

	public function testHierarchyTreeExpandLinksPreserveQueryState(): void
	{
		$rootId = $this->createHierarchyNode(
			uid: 'panel-tree-query-root',
			type: $this->parentTypeId,
			title: 'Panel Tree Query Root',
		);
		$this->createHierarchyNode(
			uid: 'panel-tree-query-child',
			type: $this->childTypeId,
			title: 'Panel Tree Query Child',
			parent: $rootId,
		);

		$response = $this->makeRequest('GET', '/cp/collection/test-hierarchy', [
			'query' => [
				'q' => 'Query Root',
				'sort' => 'uid',
				'dir' => 'asc',
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString(
			'href="/cp/collection/test-hierarchy?q=Query%20Root&amp;sort=uid&amp;dir=asc&amp;open=panel-tree-query-root"',
			$html,
		);
	}

	public function testHierarchyTreeDoesNotRenderFakeExpandControls(): void
	{
		$this->createHierarchyNode(
			uid: 'panel-tree-leaf',
			type: $this->childTypeId,
			title: 'Panel Tree Leaf',
		);

		$response = $this->makeRequest('GET', '/cp/collection/test-hierarchy');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('Panel Tree Leaf', $html);
		$this->assertStringNotContainsString(
			'aria-label="Expand children of Panel Tree Leaf"',
			$html,
		);
	}

	public function testHierarchyTreeRendersOpenedGrandchildren(): void
	{
		$rootId = $this->createHierarchyNode(
			uid: 'panel-tree-root-deep',
			type: $this->parentTypeId,
			title: 'Panel Tree Root Deep',
		);
		$childId = $this->createHierarchyNode(
			uid: 'panel-tree-child-deep',
			type: $this->parentTypeId,
			title: 'Panel Tree Child Deep',
			parent: $rootId,
		);
		$this->createHierarchyNode(
			uid: 'panel-tree-grandchild-deep',
			type: $this->childTypeId,
			title: 'Panel Tree Grandchild Deep',
			parent: $childId,
		);

		$response = $this->makeRequest('GET', '/cp/collection/test-hierarchy', [
			'query' => [
				'open' => 'panel-tree-root-deep,panel-tree-child-deep',
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('Panel Tree Root Deep', $html);
		$this->assertStringContainsString('Panel Tree Child Deep', $html);
		$this->assertStringContainsString('Panel Tree Grandchild Deep', $html);
	}

	public function testHierarchyCollectionRendersDirectChildrenByParent(): void
	{
		$rootId = $this->createHierarchyNode(
			uid: 'panel-parent-filter',
			type: $this->parentTypeId,
			title: 'Panel Parent Filter',
		);
		$childId = $this->createHierarchyNode(
			uid: 'panel-direct-child',
			type: $this->childTypeId,
			title: 'Panel Direct Child',
			parent: $rootId,
		);
		$this->createHierarchyNode(
			uid: 'panel-grandchild',
			type: $this->childTypeId,
			title: 'Panel Grandchild',
			parent: $childId,
		);

		$response = $this->makeRequest('GET', '/cp/collection/test-hierarchy', [
			'query' => [
				'parent' => 'panel-parent-filter',
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('Panel Direct Child', $html);
		$this->assertStringContainsString('<h1>Panel Parent Filter</h1>', $html);
		$this->assertStringContainsString('<span>Panel Parent Filter</span>', $html);
		$this->assertStringNotContainsString('<span>panel-parent-filter</span>', $html);
		$this->assertStringNotContainsString('Panel Grandchild', $html);
		$this->assertStringContainsString(
			'href="/cp/collection/test-hierarchy?sort=changed&amp;dir=desc"',
			$html,
		);
		$this->assertStringContainsString(
			'href="/cp/collection/test-hierarchy/create/test-hierarchy-child?sort=changed&amp;dir=desc&amp;parent=panel-parent-filter"',
			$html,
		);
		$this->assertStringContainsString('New Hierarchy Child', $html);
	}

	private function createHierarchyNode(
		string $uid,
		int $type,
		string $title,
		?int $parent = null,
	): int {
		$data = [
			'uid' => $uid,
			'type' => $type,
			'content' => [
				'title' => [
					'type' => 'text',
					'value' => ['en' => $title],
				],
			],
		];

		if ($parent !== null) {
			$data['parent'] = $parent;
		}

		return $this->createTestNode($data);
	}
}
