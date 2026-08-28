<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestHierarchyCollection;
use Cosray\Tests\Fixtures\Collection\TestRoutableHierarchyCollection;
use Cosray\Tests\Fixtures\Node\TestHierarchyChild;
use Cosray\Tests\Fixtures\Node\TestHierarchyParent;

final class PanelCollectionBulkTest extends End2EndTestCase
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

	protected function createBootstrap(Config $config): Bootstrap
	{
		$plugin = parent::createBootstrap($config);
		$plugin->node(TestHierarchyParent::class);
		$plugin->node(TestHierarchyChild::class);
		$plugin->collection(TestHierarchyCollection::class);
		$plugin->collection(TestRoutableHierarchyCollection::class);

		return $plugin;
	}

	public function testListingRendersBulkControls(): void
	{
		$this->createNode(uid: 'bulk-markup-root', title: 'Bulk Markup Root');

		$response = $this->makeRequest('GET', '/cp/collection/test-hierarchy');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('<form id="collection-bulk" method="post" hidden>', $html);
		$this->assertStringContainsString('data-bulk-bar', $html);
		$this->assertStringContainsString('data-bulk-all', $html);
		$this->assertStringContainsString('data-bulk-check', $html);
		$this->assertStringContainsString('name="nodes[]"', $html);
		$this->assertStringContainsString('value="bulk-markup-root"', $html);
		$this->assertStringContainsString('data-bulk-dialog="delete"', $html);
		$this->assertStringContainsString('data-bulk-confirm', $html);
		$this->assertStringContainsString(
			'formaction="/cp/collection/test-hierarchy/bulk/publish?sort=changed&amp;dir=desc"',
			$html,
		);
		$this->assertStringContainsString(
			'formaction="/cp/collection/test-hierarchy/bulk/delete?sort=changed&amp;dir=desc"',
			$html,
		);
	}

	public function testEmptyListingRendersNoBulkControls(): void
	{
		$response = $this->makeRequest('GET', '/cp/collection/test-hierarchy');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringNotContainsString('data-bulk-bar', $html);
		$this->assertStringNotContainsString('id="collection-bulk"', $html);
	}

	public function testRowWithChildrenMarksItsCheckbox(): void
	{
		$rootId = $this->createNode(uid: 'bulk-parent-mark', title: 'Bulk Parent');
		$this->createNode(
			uid: 'bulk-child-mark',
			title: 'Bulk Child',
			type: $this->childTypeId,
			parent: $rootId,
		);

		$response = $this->makeRequest('GET', '/cp/collection/test-hierarchy');

		$this->assertResponseOk($response);
		$this->assertStringContainsString('data-has-children', $this->getHtmlResponse($response));
	}

	public function testBulkPublishSkipsLockedAndReportsSummary(): void
	{
		$this->createNode(uid: 'bulk-pub-a', title: 'Pub A', published: false);
		$this->createNode(uid: 'bulk-pub-b', title: 'Pub B', published: true);
		$this->createNode(uid: 'bulk-pub-locked', title: 'Pub Locked', published: false, locked: true);

		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/publish', [
			'body' => [
				'nodes' => ['bulk-pub-a', 'bulk-pub-b', 'bulk-pub-locked'],
				'state' => 'published',
			],
		]);

		$this->assertResponseStatus(303, $response);
		$location = $response->getHeaderLine('Location');
		$this->assertStringStartsWith('/cp/collection/test-hierarchy', $location);
		$this->assertStringContainsString(
			'notice=' . rawurlencode('published:2,skipped-locked:1'),
			$location,
		);
		$this->assertTrue($this->nodeFlag('bulk-pub-a', 'published'));
		$this->assertFalse($this->nodeFlag('bulk-pub-locked', 'published'));
	}

	public function testBulkUnpublishSetsDraft(): void
	{
		$this->createNode(uid: 'bulk-draft-a', title: 'Draft A', published: true);

		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/publish', [
			'body' => [
				'nodes' => ['bulk-draft-a'],
				'state' => 'draft',
			],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=' . rawurlencode('drafted:1'),
			$response->getHeaderLine('Location'),
		);
		$this->assertFalse($this->nodeFlag('bulk-draft-a', 'published'));
	}

	public function testBulkPublishRejectsInvalidState(): void
	{
		$this->createNode(uid: 'bulk-state-a', title: 'State A');

		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/publish', [
			'body' => [
				'nodes' => ['bulk-state-a'],
				'state' => 'nope',
			],
		]);

		$this->assertResponseStatus(400, $response);
	}

	public function testBulkRejectsMissingEmptyAndOversizedSelections(): void
	{
		$missing = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/publish', [
			'body' => ['state' => 'published'],
		]);
		$this->assertResponseStatus(400, $missing);

		$empty = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/publish', [
			'body' => ['nodes' => [], 'state' => 'published'],
		]);
		$this->assertResponseStatus(400, $empty);

		$uids = array_map(static fn(int $i): string => "bulk-cap-{$i}", range(1, 251));
		$oversized = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/publish', [
			'body' => ['nodes' => $uids, 'state' => 'published'],
		]);
		$this->assertResponseStatus(400, $oversized);
	}

	public function testBulkDeleteRemovesLeaves(): void
	{
		$this->createNode(uid: 'bulk-del-a', title: 'Del A');
		$this->createNode(uid: 'bulk-del-b', title: 'Del B');

		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/delete', [
			'body' => ['nodes' => ['bulk-del-a', 'bulk-del-b']],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=' . rawurlencode('deleted:2'),
			$response->getHeaderLine('Location'),
		);
		$this->assertTrue($this->nodeDeleted('bulk-del-a'));
		$this->assertTrue($this->nodeDeleted('bulk-del-b'));
	}

	public function testBulkDeleteRefusesParentWithChildren(): void
	{
		$rootId = $this->createNode(uid: 'bulk-refuse-root', title: 'Refuse Root');
		$this->createNode(
			uid: 'bulk-refuse-child',
			title: 'Refuse Child',
			type: $this->childTypeId,
			parent: $rootId,
		);

		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/delete', [
			'body' => ['nodes' => ['bulk-refuse-root']],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=' . rawurlencode('skipped-children:1'),
			$response->getHeaderLine('Location'),
		);
		$this->assertFalse($this->nodeDeleted('bulk-refuse-root'));
		$this->assertFalse($this->nodeDeleted('bulk-refuse-child'));
	}

	public function testBulkDeleteWithChildrenRemovesSubtree(): void
	{
		$rootId = $this->createNode(uid: 'bulk-tree-root', title: 'Tree Root');
		$childId = $this->createNode(
			uid: 'bulk-tree-child',
			title: 'Tree Child',
			parent: $rootId,
		);
		$this->createNode(
			uid: 'bulk-tree-grandchild',
			title: 'Tree Grandchild',
			type: $this->childTypeId,
			parent: $childId,
		);

		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/delete', [
			'body' => ['nodes' => ['bulk-tree-root'], 'children' => '1'],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=' . rawurlencode('deleted:3'),
			$response->getHeaderLine('Location'),
		);
		$this->assertTrue($this->nodeDeleted('bulk-tree-root'));
		$this->assertTrue($this->nodeDeleted('bulk-tree-child'));
		$this->assertTrue($this->nodeDeleted('bulk-tree-grandchild'));
	}

	public function testBulkDeleteCountsSubtreeMembersOnlyOnce(): void
	{
		$rootId = $this->createNode(uid: 'bulk-overlap-root', title: 'Overlap Root');
		$childId = $this->createNode(
			uid: 'bulk-overlap-child',
			title: 'Overlap Child',
			parent: $rootId,
		);
		$this->createNode(
			uid: 'bulk-overlap-grandchild',
			title: 'Overlap Grandchild',
			type: $this->childTypeId,
			parent: $childId,
		);

		// Root and grandchild selected, subtree delete on: whichever is
		// processed first, three nodes fall and none is counted twice.
		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/delete', [
			'body' => [
				'nodes' => ['bulk-overlap-root', 'bulk-overlap-grandchild'],
				'children' => '1',
			],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=' . rawurlencode('deleted:3'),
			$response->getHeaderLine('Location'),
		);
		$this->assertTrue($this->nodeDeleted('bulk-overlap-root'));
		$this->assertTrue($this->nodeDeleted('bulk-overlap-child'));
		$this->assertTrue($this->nodeDeleted('bulk-overlap-grandchild'));
	}

	public function testBulkDeleteOrdersSelectedChildrenBeforeParents(): void
	{
		$rootId = $this->createNode(uid: 'bulk-order-root', title: 'Order Root');
		$this->createNode(
			uid: 'bulk-order-child',
			title: 'Order Child',
			type: $this->childTypeId,
			parent: $rootId,
		);

		// Without the children flag: deleting the child first leaves the
		// parent childless, so both go in one request.
		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/delete', [
			'body' => ['nodes' => ['bulk-order-root', 'bulk-order-child']],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=' . rawurlencode('deleted:2'),
			$response->getHeaderLine('Location'),
		);
		$this->assertTrue($this->nodeDeleted('bulk-order-root'));
		$this->assertTrue($this->nodeDeleted('bulk-order-child'));
	}

	public function testBulkDeleteSkipsLockedAndUnknownNodes(): void
	{
		$this->createNode(uid: 'bulk-skip-locked', title: 'Skip Locked', locked: true);

		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/delete', [
			'body' => ['nodes' => ['bulk-skip-locked', 'bulk-skip-unknown']],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=' . rawurlencode('skipped-locked:1,skipped:1'),
			$response->getHeaderLine('Location'),
		);
		$this->assertFalse($this->nodeDeleted('bulk-skip-locked'));
	}

	public function testBulkSkipsNodesOutsideTheCollection(): void
	{
		$foreignType = $this->createTestType('bulk-foreign-type');
		$this->createNode(uid: 'bulk-foreign', title: 'Foreign', type: $foreignType);

		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/delete', [
			'body' => ['nodes' => ['bulk-foreign']],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=' . rawurlencode('skipped:1'),
			$response->getHeaderLine('Location'),
		);
		$this->assertFalse($this->nodeDeleted('bulk-foreign'));
	}

	public function testBulkRedirectKeepsTheListingQuery(): void
	{
		$this->createNode(uid: 'bulk-query-a', title: 'Query A', published: false);

		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/publish', [
			'query' => ['view' => 'list', 'q' => 'Query'],
			'body' => ['nodes' => ['bulk-query-a'], 'state' => 'published'],
		]);

		$this->assertResponseStatus(303, $response);
		$location = $response->getHeaderLine('Location');
		$this->assertStringContainsString('view=list', $location);
		$this->assertStringContainsString('q=Query', $location);
	}

	public function testBulkRequiresAuthentication(): void
	{
		$this->createNode(uid: 'bulk-auth-a', title: 'Auth A');
		$this->defaultAuthToken = null;

		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/delete', [
			'body' => ['nodes' => ['bulk-auth-a']],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringStartsWith('/cp/login', $response->getHeaderLine('Location'));
		$this->assertFalse($this->nodeDeleted('bulk-auth-a'));
	}

	public function testBulkRejectsUnknownCollection(): void
	{
		$response = $this->makeRequest('POST', '/cp/collection/no-such-collection/bulk/delete', [
			'body' => ['nodes' => ['bulk-any']],
		]);

		$this->assertResponseStatus(404, $response);
	}

	public function testListingRendersDuplicateDialog(): void
	{
		$this->createNode(uid: 'bulk-dup-markup', title: 'Dup Markup');

		$response = $this->makeRequest('GET', '/cp/collection/test-hierarchy');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('data-bulk-dialog="duplicate"', $html);
		$this->assertStringContainsString('data-bulk-open="duplicate"', $html);
		$this->assertStringContainsString(
			'formaction="/cp/collection/test-hierarchy/bulk/duplicate?sort=changed&amp;dir=desc"',
			$html,
		);
		$this->assertStringContainsString('data-bulk-children data-bulk-gate', $html);
	}

	public function testBulkDuplicateCreatesAnUnlockedDraftCopy(): void
	{
		$this->createNode(
			uid: 'bulk-dup-source',
			title: 'Dup Source',
			published: true,
			locked: true,
			handle: 'bulk-dup-source-handle',
		);

		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/duplicate', [
			'body' => ['nodes' => ['bulk-dup-source']],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=' . rawurlencode('duplicated:1'),
			$response->getHeaderLine('Location'),
		);

		$copy = $this->db()->execute(
			"SELECT n.uid, n.published, n.locked, n.parent, n.content::text AS content,
				(SELECT s.content::text FROM cms.nodes s WHERE s.uid = 'bulk-dup-source') AS source
			FROM cms.nodes n
			WHERE n.type = :type AND n.uid <> 'bulk-dup-source'",
			['type' => $this->parentTypeId],
		)->one();

		$this->assertNotSame('bulk-dup-source', $copy['uid']);
		$this->assertFalse((bool) $copy['published']);
		$this->assertFalse((bool) $copy['locked']);
		$this->assertNull($copy['parent']);
		$this->assertSame($copy['source'], $copy['content']);
		$handles = $this->db()->execute(
			"SELECT count(*) AS count FROM cms.node_handles WHERE handle LIKE 'bulk-dup-source%'",
		)->one();
		$this->assertSame(1, (int) $handles['count']);
	}

	public function testBulkDuplicateWithChildrenRemapsTheSubtree(): void
	{
		$rootId = $this->createNode(uid: 'bulk-dup-root', title: 'Dup Root');
		$childId = $this->createNode(uid: 'bulk-dup-child', title: 'Dup Child', parent: $rootId);
		$this->createNode(
			uid: 'bulk-dup-grandchild',
			title: 'Dup Grandchild',
			type: $this->childTypeId,
			parent: $childId,
		);

		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/duplicate', [
			'body' => ['nodes' => ['bulk-dup-root'], 'children' => '1'],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=' . rawurlencode('duplicated:3'),
			$response->getHeaderLine('Location'),
		);

		$rootCopy = $this->db()->execute(
			"SELECT node FROM cms.nodes
			WHERE type = :type AND parent IS NULL AND uid <> 'bulk-dup-root'",
			['type' => $this->parentTypeId],
		)->one();
		$childCopy = $this->db()->execute(
			'SELECT node, published FROM cms.nodes WHERE parent = :parent',
			['parent' => $rootCopy['node']],
		)->one();
		$grandchildCopy = $this->db()->execute(
			'SELECT uid FROM cms.nodes WHERE parent = :parent',
			['parent' => $childCopy['node']],
		)->one();

		$this->assertFalse((bool) $childCopy['published']);
		$this->assertNotSame('bulk-dup-grandchild', $grandchildCopy['uid']);
	}

	public function testBulkDuplicateSkipsDescendantsOfSelectedAncestors(): void
	{
		$rootId = $this->createNode(uid: 'bulk-dup-cov-root', title: 'Cov Root');
		$childId = $this->createNode(uid: 'bulk-dup-cov-child', title: 'Cov Child', parent: $rootId);
		$this->createNode(
			uid: 'bulk-dup-cov-grandchild',
			title: 'Cov Grandchild',
			type: $this->childTypeId,
			parent: $childId,
		);

		// Grandchild's chain to the selected root runs through an unselected
		// node; the subtree copy of the root must still cover it.
		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/duplicate', [
			'body' => [
				'nodes' => ['bulk-dup-cov-root', 'bulk-dup-cov-grandchild'],
				'children' => '1',
			],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=' . rawurlencode('duplicated:3'),
			$response->getHeaderLine('Location'),
		);
	}

	public function testBulkDuplicateWithoutChildrenCopiesOnlyTheNode(): void
	{
		$rootId = $this->createNode(uid: 'bulk-dup-flat-root', title: 'Flat Root');
		$this->createNode(
			uid: 'bulk-dup-flat-child',
			title: 'Flat Child',
			type: $this->childTypeId,
			parent: $rootId,
		);

		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/duplicate', [
			'body' => ['nodes' => ['bulk-dup-flat-root']],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=' . rawurlencode('duplicated:1'),
			$response->getHeaderLine('Location'),
		);

		$copy = $this->db()->execute(
			"SELECT node FROM cms.nodes
			WHERE type = :type AND parent IS NULL AND uid <> 'bulk-dup-flat-root'",
			['type' => $this->parentTypeId],
		)->one();
		$children = $this->db()->execute(
			'SELECT count(*) AS count FROM cms.nodes WHERE parent = :parent',
			['parent' => $copy['node']],
		)->one();

		$this->assertSame(0, (int) $children['count']);
	}

	public function testBulkDuplicateSkipsUnknownNodes(): void
	{
		$this->createNode(uid: 'bulk-dup-known', title: 'Dup Known');

		$response = $this->makeRequest('POST', '/cp/collection/test-hierarchy/bulk/duplicate', [
			'body' => ['nodes' => ['bulk-dup-known', 'bulk-dup-unknown']],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'notice=' . rawurlencode('duplicated:1,skipped:1'),
			$response->getHeaderLine('Location'),
		);
	}

	public function testBulkDuplicateGeneratesSuffixedPathsForRoutableCopies(): void
	{
		$routableType = $this->createTestType('optional-parent-path-route-page');
		$this->createNode(uid: 'bulk-dup-route', title: 'Route Source', type: $routableType);

		// Twice: the first copy generates the base path (the raw-SQL source
		// has none), the second collides with the first and gets suffixed.
		foreach ([1, 2] as $round) {
			$response = $this->makeRequest(
				'POST',
				'/cp/collection/test-routable-hierarchy/bulk/duplicate',
				['body' => ['nodes' => ['bulk-dup-route']]],
			);
			$this->assertResponseStatus(303, $response, "duplication round {$round}");
		}

		$paths = array_column(
			$this->db()->execute(
				"SELECT p.path FROM cms.url_paths p
				JOIN cms.nodes n ON n.node = p.node
				WHERE n.type = :type AND n.uid <> 'bulk-dup-route' AND p.locale = 'en'
				ORDER BY p.created",
				['type' => $routableType],
			)->all(),
			'path',
		);

		$this->assertCount(2, $paths);
		$this->assertNotSame($paths[0], $paths[1]);
		$this->assertStringStartsWith($paths[0] . '-', $paths[1]);
	}

	public function testBulkDuplicateComposesChildPathsUnderTheCopiedParent(): void
	{
		$routableType = $this->createTestType('optional-parent-path-route-page');
		$rootId = $this->createNode(
			uid: 'bulk-dup-route-root',
			title: 'Route Root',
			type: $routableType,
		);
		$this->createNode(
			uid: 'bulk-dup-route-child',
			title: 'Route Child',
			type: $routableType,
			parent: $rootId,
		);

		$response = $this->makeRequest(
			'POST',
			'/cp/collection/test-routable-hierarchy/bulk/duplicate',
			['body' => ['nodes' => ['bulk-dup-route-root'], 'children' => '1']],
		);

		$this->assertResponseStatus(303, $response);

		$rootCopy = $this->db()->execute(
			"SELECT n.node, p.path FROM cms.nodes n
			JOIN cms.url_paths p ON p.node = n.node AND p.locale = 'en'
			WHERE n.type = :type AND n.parent IS NULL AND n.uid <> 'bulk-dup-route-root'",
			['type' => $routableType],
		)->one();
		$childCopy = $this->db()->execute(
			"SELECT p.path FROM cms.nodes n
			JOIN cms.url_paths p ON p.node = n.node AND p.locale = 'en'
			WHERE n.parent = :parent",
			['parent' => $rootCopy['node']],
		)->one();

		$this->assertStringStartsWith(
			rtrim((string) $rootCopy['path'], '/') . '/',
			(string) $childCopy['path'],
		);
	}

	public function testNoticeParamRendersBannerAndIgnoresGarbage(): void
	{
		$this->createNode(uid: 'bulk-notice-a', title: 'Notice A');

		$response = $this->makeRequest('GET', '/cp/collection/test-hierarchy', [
			'query' => ['notice' => 'published:2,hack:9,deleted:x,skipped-locked:1'],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('cms-notice', $html);
		$this->assertStringContainsString('2 entries published', $html);
		$this->assertStringContainsString('1 locked entry skipped', $html);
		$this->assertStringNotContainsString('hack', $html);
	}

	public function testEditorDeleteRefusesParentWithChildren(): void
	{
		$rootId = $this->createNode(uid: 'bulk-editor-root', title: 'Editor Root');
		$this->createNode(
			uid: 'bulk-editor-child',
			title: 'Editor Child',
			type: $this->childTypeId,
			parent: $rootId,
		);

		$response = $this->makeRequest(
			'POST',
			'/cp/collection/test-hierarchy/bulk-editor-root/delete',
		);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('The entry has child entries', $html);
		$this->assertFalse($this->nodeDeleted('bulk-editor-root'));
	}

	private function createNode(
		string $uid,
		string $title,
		?int $type = null,
		?int $parent = null,
		bool $published = true,
		bool $locked = false,
		?string $handle = null,
	): int {
		$data = [
			'uid' => $uid,
			'type' => $type ?? $this->parentTypeId,
			'published' => $published,
			'locked' => $locked,
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

		if ($handle !== null) {
			$data['handle'] = $handle;
		}

		return $this->createTestNode($data);
	}

	private function nodeFlag(string $uid, string $flag): bool
	{
		$row = $this->db()->execute(
			"SELECT {$flag} FROM cms.nodes WHERE uid = :uid",
			['uid' => $uid],
		)->one();

		return (bool) $row[$flag];
	}

	private function nodeDeleted(string $uid): bool
	{
		$row = $this->db()->execute(
			'SELECT deleted FROM cms.nodes WHERE uid = :uid',
			['uid' => $uid],
		)->one();

		return $row['deleted'] !== null;
	}
}
