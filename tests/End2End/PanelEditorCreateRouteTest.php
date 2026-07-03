<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestHierarchyCollection;
use Cosray\Tests\Fixtures\Collection\TestRoutableCollection;
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
		$this->parentTypeId = $this->ensureTestType('test-hierarchy-parent');
		$this->childTypeId = $this->ensureTestType('test-hierarchy-child');
		$this->ensureTestType('parent-path-route-page');
	}

	/**
	 * Reuse a type row left behind by a crashed run and adopt it (and
	 * any orphaned nodes referencing it) into the cleanup tracking, so
	 * teardown restores the clean state other test classes rely on.
	 */
	private function ensureTestType(string $handle): int
	{
		$type = $this->db()->execute(
			'SELECT type FROM cms.types WHERE handle = :handle',
			['handle' => $handle],
		)->first();

		if (!$type) {
			return $this->createTestType($handle);
		}

		$nodes = $this->db()->execute(
			'SELECT node FROM cms.nodes WHERE type = :type ORDER BY node',
			['type' => (int) $type['type']],
		)->all();

		foreach ($nodes as $node) {
			$this->trackNodeId((int) $node['node']);
		}

		$this->createdTypeHandles[] = $handle;

		return (int) $type['type'];
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$plugin = parent::createBootstrap($config);
		$plugin->node(TestHierarchyParent::class);
		$plugin->node(TestHierarchyChild::class);
		$plugin->collection(TestHierarchyCollection::class);
		$plugin->collection(TestRoutableCollection::class);

		return $plugin;
	}

	public function testPanelCreateRouteRendersShellForAllowedType(): void
	{
		$this->createHierarchyNode(
			uid: 'panel-create-parent',
			type: $this->parentTypeId,
			title: 'Panel Create Parent',
		);

		$response = $this->makeRequest(
			'GET',
			'/cp/collection/test-hierarchy/create/test-hierarchy-child',
			[
				'query' => [
					'parent' => 'panel-create-parent',
					'q' => 'Hierarchy',
					'sort' => 'uid',
					'dir' => 'asc',
					'view' => 'tree',
					'open' => 'panel-create-parent',
				],
			],
		);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('id="main" class="page node"', $html);
		$this->assertStringNotContainsString('Back to list', $html);
		$this->assertStringNotContainsString('topbar-editor', $html);
		$this->assertCreateAssetStateIsRendered($html);
	}

	public function testPanelCreateRouteRejectsChildTypeWithoutParent(): void
	{
		$response = $this->makeRequest(
			'GET',
			'/cp/collection/test-hierarchy/create/test-hierarchy-child',
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
			'href="/cp/collection/test-hierarchy/create/test-hierarchy-parent?sort=changed&amp;dir=desc"',
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

	public function testCreatePostStoresTheNodeAndRedirectsToItsEditor(): void
	{
		// A crashed run leaves the parent (and its stored child) behind;
		// adopt them into the cleanup list instead of colliding.
		if (!$this->adoptNodeWithChildren('panel-store-parent')) {
			$this->createHierarchyNode(
				uid: 'panel-store-parent',
				type: $this->parentTypeId,
				title: 'Panel Store Parent',
			);
		}

		$response = $this->makeRequest(
			'POST',
			'/cp/collection/test-hierarchy/create/test-hierarchy-child',
			[
				'query' => ['parent' => 'panel-store-parent'],
				'body' => [
					'content' => [
						'title' => ['value' => ['en' => 'Stored Child']],
					],
				],
			],
		);

		$this->assertResponseStatus(303, $response);
		$location = $response->getHeaderLine('Location');
		$this->assertMatchesRegularExpression(
			'#^/cp/collection/test-hierarchy/[A-Za-z0-9_-]+\?#',
			$location,
		);

		$uid = explode('?', basename($location))[0];
		$this->trackNodeByUid($uid);
		$row = $this->db()->execute(
			'SELECT content, parent FROM cms.nodes WHERE uid = :uid',
			['uid' => $uid],
		)->one();
		$content = json_decode((string) $row['content'], true);
		$this->assertSame('Stored Child', $content['title']['value']['en']);
		$this->assertNotNull($row['parent']);
	}

	public function testCreateRouteCarriesBlueprintUidForPreSaveMedia(): void
	{
		$this->createHierarchyNode(
			uid: 'panel-create-uid-parent',
			type: $this->parentTypeId,
			title: 'Panel Create Uid Parent',
		);
		$response = $this->makeRequest(
			'GET',
			'/cp/collection/test-hierarchy/create/test-hierarchy-child',
			['query' => ['parent' => 'panel-create-uid-parent']],
		);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		// A new node exposes the pre-generated uid so uploads land under
		// node/<uid>/ before the first save.
		$this->assertMatchesRegularExpression(
			'/<input type="hidden" name="uid" value="[A-Za-z0-9._-]{1,64}"/',
			$html,
		);
	}

	public function testCreatePostAdoptsTheSubmittedBlueprintUid(): void
	{
		if (!$this->adoptNodeWithChildren('panel-store-uid-parent')) {
			$this->createHierarchyNode(
				uid: 'panel-store-uid-parent',
				type: $this->parentTypeId,
				title: 'Panel Store Uid Parent',
			);
		}

		// Mirror the real flow: the create form pre-generates a uid, the
		// client uploads to it, then submits it so the saved node adopts it.
		$get = $this->makeRequest(
			'GET',
			'/cp/collection/test-hierarchy/create/test-hierarchy-child',
			['query' => ['parent' => 'panel-store-uid-parent']],
		);
		preg_match('/name="uid" value="([A-Za-z0-9._-]+)"/', $this->getHtmlResponse($get), $m);
		$uid = $m[1] ?? '';
		$this->assertNotSame('', $uid);

		$response = $this->makeRequest(
			'POST',
			'/cp/collection/test-hierarchy/create/test-hierarchy-child',
			[
				'query' => ['parent' => 'panel-store-uid-parent'],
				'body' => [
					'uid' => $uid,
					'content' => ['title' => ['value' => ['en' => 'Child With Uid']]],
				],
			],
		);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'/cp/collection/test-hierarchy/' . $uid . '?',
			$response->getHeaderLine('Location'),
		);
		$this->trackNodeByUid($uid);
		$row = $this->db()->execute(
			'SELECT uid FROM cms.nodes WHERE uid = :uid',
			['uid' => $uid],
		)->one();
		$this->assertSame($uid, $row['uid']);
	}

	public function testCreateRouteForRoutableTypeWiresTheLivePreview(): void
	{
		$response = $this->makeRequest(
			'GET',
			'/cp/collection/test-routable/create/parent-path-route-page',
		);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		// The preview posts to the blueprint-based create-paths endpoint...
		$this->assertStringContainsString('id="generated-paths"', $html);
		$this->assertStringContainsString(
			'/cp/collection/test-routable/create/parent-path-route-page/paths',
			$html,
		);
		// ...and the {title} field the route references is marked so editing
		// it refreshes the preview.
		$this->assertStringContainsString('<div class="js-path-source"', $html);
	}

	public function testCreatePathsPreviewsFromTheBlueprint(): void
	{
		$response = $this->makeRequest(
			'POST',
			'/cp/collection/test-routable/create/parent-path-route-page/paths',
			['body' => ['content' => ['title' => ['value' => ['en' => 'Fresh Title']]]]],
		);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		// /{parent}/{title} -> the title slug previews (parent is a placeholder).
		$this->assertStringContainsString('fresh-title', $html);
	}

	private function assertCreateAssetStateIsRendered(string $html): void
	{
		// The editor is a server-rendered form regardless of the panel build.
		$this->assertStringContainsString('class="cms-node-form"', $html);
		$this->assertStringContainsString(
			'action="/cp/collection/test-hierarchy/create/test-hierarchy-child?q=Hierarchy&amp;sort=uid&amp;dir=asc&amp;parent=panel-create-parent&amp;view=tree&amp;open=panel-create-parent"',
			$html,
		);
		$this->assertStringContainsString('name="content[title][value][en]"', $html);
		$this->assertStringContainsString('cms-headline-title', $html);
		$this->assertStringNotContainsString('id="cosray-node-editor"', $html);
		$this->assertStringNotContainsString('Panel bundle missing', $html);
	}

	/** Track a leftover node and its children for teardown cleanup. */
	private function adoptNodeWithChildren(string $uid): bool
	{
		$rows = $this->db()->execute(
			'SELECT node FROM cms.nodes
			WHERE uid = :uid
				OR parent IN (SELECT node FROM cms.nodes WHERE uid = :uid)
			ORDER BY node',
			['uid' => $uid],
		)->all();

		foreach ($rows as $row) {
			$this->trackNodeId((int) $row['node']);
		}

		return $rows !== [];
	}

	private function trackNodeId(int $nodeId): void
	{
		$this->createdNodeIds[] = $nodeId;
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
