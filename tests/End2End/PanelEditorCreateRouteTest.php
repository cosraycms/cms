<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Schema\Children;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestHierarchyCollection;
use Cosray\Tests\Fixtures\Collection\TestRoutableCollection;
use Cosray\Tests\Fixtures\Node\ParentPathRoutePage;
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
		$this->createTestType('parent-path-route-page');
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$plugin = parent::createBootstrap($config);
		$plugin->node(TestHierarchyParent::class);
		$plugin->node(PreviewParent::class);
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
			'/cp/node/create/test-hierarchy-child',
			[
				'query' => [
					'parent' => 'panel-create-parent',
					'from' => 'collection:test-hierarchy',
					'list' => [
						'q' => 'Hierarchy',
						'sort' => 'uid',
						'dir' => 'asc',
						'view' => 'tree',
						'open' => 'panel-create-parent',
					],
				],
			],
		);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('class="page cms-node"', $html);
		$this->assertStringNotContainsString('Back to list', $html);
		$this->assertStringNotContainsString('topbar-editor', $html);
		$this->assertCreateAssetStateIsRendered($html);
	}

	public function testRegisteredTypeCanBeCreatedWithoutCollectionOrParent(): void
	{
		$response = $this->makeRequest('GET', '/cp/node/create/test-hierarchy-child');

		$this->assertResponseOk($response);
		$this->assertHtmlNodeExists(
			'//form[@id="node-editor-form"][@action="/cp/node/create/test-hierarchy-child"]',
			$this->getHtmlResponse($response),
		);
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
			'href="/cp/node/create/test-hierarchy-parent?from=collection%3Atest-hierarchy&amp;list%5Bsort%5D=changed&amp;list%5Bdir%5D=desc"',
			$html,
		);
		$this->assertStringContainsString(
			'href="/cp/node/create/test-hierarchy-child?from=collection%3Atest-hierarchy&amp;list%5Bsort%5D=changed&amp;list%5Bdir%5D=desc&amp;parent=panel-create-parent"',
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
			'href="/cp/node/create/test-hierarchy-child?from=collection%3Atest-hierarchy&amp;list%5Bsort%5D=changed&amp;list%5Bdir%5D=desc&amp;list%5Bparent%5D=panel-current-parent&amp;parent=panel-current-parent"',
			$html,
		);
	}

	public function testCreatePostStoresTheNodeAndRedirectsToItsEditor(): void
	{
		$this->createHierarchyNode(
			uid: 'panel-store-parent',
			type: $this->parentTypeId,
			title: 'Panel Store Parent',
		);

		$response = $this->makeRequest(
			'POST',
			'/cp/node/create/test-hierarchy-child',
			[
				'query' => ['parent' => 'panel-store-parent'],
				'body' => [
					'_complete' => '1',
					'content' => [
						'title' => ['value' => ['en' => 'Stored Child']],
					],
				],
			],
		);

		$this->assertResponseStatus(303, $response);
		$location = $response->getHeaderLine('Location');
		$this->assertMatchesRegularExpression(
			'#^/cp/node/[A-Za-z0-9_-]+$#',
			$location,
		);

		$uid = explode('?', basename($location))[0];
		$row = $this->db()->execute(
			'SELECT content, parent FROM cms.nodes WHERE uid = :uid',
			['uid' => $uid],
		)->one();
		$content = json_decode((string) $row['content'], true);
		$this->assertSame('Stored Child', $content['title']['value']['en']);
		$this->assertNotNull($row['parent']);
	}

	public function testCreationParentIsIndependentOfTheReturnListing(): void
	{
		$listParent = $this->createHierarchyNode('list-parent', $this->parentTypeId, 'List parent');
		$actualParent = $this->createHierarchyNode('actual-parent', $this->parentTypeId, 'Actual parent');
		$query = [
			'from' => 'collection:test-hierarchy',
			'list' => ['parent' => 'list-parent', 'offset' => 50, 'q' => 'Find me'],
			'parent' => 'actual-parent',
		];
		$response = $this->makeRequest('GET', '/cp/node/create/test-hierarchy-child', ['query' => $query]);
		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		preg_match('/id="node-editor-form"[^>]*action="([^"]+)"/s', $html, $matches);
		$action = html_entity_decode($matches[1] ?? '', ENT_QUOTES);
		parse_str((string) parse_url($action, PHP_URL_QUERY), $params);
		$this->assertEquals($query, $params);

		$response = $this->makeRequest('POST', (string) parse_url($action, PHP_URL_PATH), [
			'query' => $params,
			'body' => [
				'_complete' => '1',
				'uid' => 'independent-parent',
				'content' => [
					'title' => ['value' => ['en' => 'New child']],
				],
			],
		]);
		$this->assertResponseStatus(303, $response);
		$location = $response->getHeaderLine('Location');
		parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
		$this->assertEquals(
			[
				'from' => $query['from'],
				'list' => $query['list'],
			],
			$params,
		);
		$row = $this->db()->execute('SELECT parent FROM cms.nodes WHERE uid = :uid', [
			'uid' => 'independent-parent',
		])->one();
		$this->assertSame($actualParent, (int) $row['parent']);
		$this->assertNotSame($listParent, (int) $row['parent']);

		$editor = $this->makeRequest('GET', (string) parse_url($location, PHP_URL_PATH), ['query' => $params]);
		$this->assertResponseOk($editor);
		$this->assertHtmlNodeExists(
			'//nav[@class="breadcrumb"]/a[@href="/cp/collection/test-hierarchy?q=Find%20me&offset=50&parent=list-parent"]',
			$this->getHtmlResponse($editor),
		);
	}

	public function testCreationRejectsUnknownTypesAndInvalidParentsAcrossEndpoints(): void
	{
		$this->createHierarchyNode('valid-parent', $this->parentTypeId, 'Parent');
		$this->createHierarchyNode('leaf-parent', $this->childTypeId, 'Leaf');

		foreach ([
			['unknown-type', null],
			['test-hierarchy-child', 'missing-parent'],
			['test-hierarchy-child', 'leaf-parent'],
			['parent-path-route-page', 'valid-parent'],
		] as [$type, $parent]) {
			foreach ([['GET', ''], ['POST', ''], ['POST', '/paths'], ['POST', '/blocks/content']] as [
				$method,
				$suffix,
			]) {
				$response = $this->makeRequest($method, '/cp/node/create/' . $type . $suffix, [
					'query' => $parent === null ? [] : ['parent' => $parent],
					'body' => ['_complete' => '1'],
				]);
				$this->assertResponseStatus(404, $response, "{$method} {$type}{$suffix} parent={$parent}");
			}
		}
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
			'/cp/node/create/test-hierarchy-child',
			['query' => ['parent' => 'panel-create-uid-parent']],
		);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		// A new node exposes the pre-generated uid so uploads land under
		// node/<uid>/ before the first save.
		$this->assertHtmlNodeExists(
			'//input[@type="hidden" and @name="uid" and string-length(@value) >= 1 and string-length(@value) <= 64 and translate(@value, "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789._-", "") = ""]',
			$html,
		);
	}

	public function testCreatePostAdoptsTheSubmittedBlueprintUid(): void
	{
		$this->createHierarchyNode(
			uid: 'panel-store-uid-parent',
			type: $this->parentTypeId,
			title: 'Panel Store Uid Parent',
		);

		// Mirror the real flow: the create form pre-generates a uid, the
		// client uploads to it, then submits it so the saved node adopts it.
		$get = $this->makeRequest(
			'GET',
			'/cp/node/create/test-hierarchy-child',
			['query' => ['parent' => 'panel-store-uid-parent']],
		);
		preg_match('/name="uid" value="([A-Za-z0-9._-]+)"/', $this->getHtmlResponse($get), $m);
		$uid = $m[1] ?? '';
		$this->assertNotSame('', $uid);

		$response = $this->makeRequest(
			'POST',
			'/cp/node/create/test-hierarchy-child',
			[
				'query' => ['parent' => 'panel-store-uid-parent'],
				'body' => [
					'_complete' => '1',
					'uid' => $uid,
					'content' => ['title' => ['value' => ['en' => 'Child With Uid']]],
				],
			],
		);

		$this->assertResponseStatus(303, $response);
		$this->assertStringContainsString(
			'/cp/node/' . $uid,
			$response->getHeaderLine('Location'),
		);
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
			'/cp/node/create/parent-path-route-page',
		);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		// The preview posts to the blueprint-based create-paths endpoint...
		$this->assertStringContainsString('id="generated-paths"', $html);
		$this->assertStringContainsString(
			'/cp/node/create/parent-path-route-page/paths',
			$html,
		);
		// ...and the {title} field the route references is marked so editing
		// it refreshes the preview.
		$this->assertHtmlNodeExists(
			'//div[contains(concat(" ", normalize-space(@class), " "), " js-path-source ")]'
				. '//input[starts-with(@name, "content[title][value]")]',
			$html,
		);
	}

	public function testCreatePathsPreviewsFromTheBlueprint(): void
	{
		$response = $this->makeRequest(
			'POST',
			'/cp/node/create/parent-path-route-page/paths',
			['body' => ['content' => ['title' => ['value' => ['en' => 'Fresh Title']]]]],
		);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		// /{parent}/{title} -> the title slug previews (parent is a placeholder).
		$this->assertStringContainsString('fresh-title', $html);
	}

	public function testCreationPathPreviewUsesTheActualParent(): void
	{
		$type = $this->createTestType('preview-parent');
		$parent = $this->createHierarchyNode('preview-parent-node', $type, 'Preview parent');
		$this->createTestPath($parent, '/parent-path');

		$response = $this->makeRequest('GET', '/cp/node/create/parent-path-route-page', [
			'query' => ['parent' => 'preview-parent-node'],
		]);
		$this->assertResponseOk($response);
		$this->assertHtmlNodeExists(
			'//*[@hx-post="/cp/node/create/parent-path-route-page/paths?parent=preview-parent-node"]',
			$this->getHtmlResponse($response),
		);

		$response = $this->makeRequest('POST', '/cp/node/create/parent-path-route-page/paths', [
			'query' => ['parent' => 'preview-parent-node'],
			'body' => ['content' => ['title' => ['value' => ['en' => 'Fresh title']]]],
		]);
		$this->assertResponseOk($response);
		$this->assertStringContainsString('/parent-path/fresh-title', $this->getHtmlResponse($response));
	}

	private function assertCreateAssetStateIsRendered(string $html): void
	{
		// The editor is a server-rendered form regardless of the panel static assets.
		$this->assertStringContainsString('id="node-editor-form"', $html);
		$this->assertStringContainsString('class="panes"', $html);
		$this->assertStringContainsString(
			'action="/cp/node/create/test-hierarchy-child?from=collection%3Atest-hierarchy&amp;list%5Bq%5D=Hierarchy&amp;list%5Bsort%5D=uid&amp;list%5Bdir%5D=asc&amp;list%5Bopen%5D=panel-create-parent&amp;parent=panel-create-parent"',
			$html,
		);
		$this->assertStringContainsString('name="content[title][value][en]"', $html);
		// The title lives in the header beside the status pill, outside the
		// scrolling panes.
		$this->assertStringContainsString('<div class="line">', $html);
		$this->assertStringNotContainsString('id="cosray-node-editor"', $html);
		$this->assertStringNotContainsString('Panel bundle missing', $html);
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

#[Children(ParentPathRoutePage::class)]
final class PreviewParent extends TestHierarchyParent {}
