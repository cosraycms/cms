<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Config;
use Cosray\Plugin;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestHierarchyCollection;
use Cosray\Tests\Fixtures\Node\TestHierarchyChild;
use Cosray\Tests\Fixtures\Node\TestHierarchyParent;

final class PanelEditorCreateRouteTest extends End2EndTestCase
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

	public function testPanelCreateRouteRendersShellForAllowedType(): void
	{
		$response = $this->makeRequest(
			'GET',
			'/cp/collection/test-hierarchy/create/test-hierarchy-child',
			[
				'query' => [
					'parent' => 'panel-create-parent',
					'q' => 'Hierarchy',
					'sort' => 'uid',
					'dir' => 'asc',
				],
			],
		);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('id="main" class="page editor-page"', $html);
		$this->assertStringContainsString('<h1>Test hierarchy</h1>', $html);
		$this->assertCreateAssetStateIsRendered($html);
	}

	public function testPanelCreateRouteRejectsInvalidType(): void
	{
		$response = $this->makeRequest(
			'GET',
			'/cp/collection/test-hierarchy/create/test-hierarchy-parent',
		);

		$this->assertResponseStatus(404, $response);
	}

	public function testCollectionListLinksToCreateRouteWithParent(): void
	{
		$this->createHierarchyNode(
			uid: 'panel-create-parent',
			type: $this->parentTypeId,
			title: 'Panel Create Parent',
		);
		$response = $this->makeRequest('GET', '/cp/collection/test-hierarchy');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString(
			'href="/cp/collection/test-hierarchy/create/test-hierarchy-child?sort=changed&amp;dir=desc"',
			$html,
		);
		$this->assertStringContainsString(
			'href="/cp/collection/test-hierarchy/create/test-hierarchy-child?sort=changed&amp;dir=desc&amp;parent=panel-create-parent"',
			$html,
		);
	}

	public function testCollectionCreateLinkPreservesCurrentParent(): void
	{
		$parentId = $this->createHierarchyNode(
			uid: 'panel-current-parent',
			type: $this->parentTypeId,
			title: 'Panel Current Parent',
		);
		$this->createHierarchyNode(
			uid: 'panel-current-child',
			type: $this->childTypeId,
			title: 'Panel Current Child',
			parent: $parentId,
		);
		$response = $this->makeRequest('GET', '/cp/collection/test-hierarchy', [
			'query' => [
				'parent' => 'panel-current-parent',
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString(
			'href="/cp/collection/test-hierarchy/create/test-hierarchy-child?sort=changed&amp;dir=desc&amp;parent=panel-current-parent"',
			$html,
		);
	}

	private function assertCreateAssetStateIsRendered(string $html): void
	{
		if (is_file(dirname(__DIR__, 2) . '/panel/build/panel.js')) {
			$this->assertStringContainsString('id="cosray-node-editor"', $html);
			$this->assertStringContainsString('src="/cp/assets/build/panel.js"', $html);
			$this->assertStringContainsString('"mode":"create"', $html);
			$this->assertStringContainsString('"type":"test-hierarchy-child"', $html);
			$this->assertStringContainsString('"parent":"panel-create-parent"', $html);

			return;
		}

		$this->assertStringContainsString('Panel bundle missing', $html);
		$this->assertStringContainsString(
			'href="/panel/collection/test-hierarchy/create/test-hierarchy-child?q=Hierarchy&amp;sort=uid&amp;dir=asc&amp;parent=panel-create-parent"',
			$html,
		);
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
