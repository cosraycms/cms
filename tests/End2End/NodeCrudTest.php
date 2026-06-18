<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;
use Cosray\Uid;

/**
 * End-to-end tests for Node CRUD operations through HTTP API.
 *
 * @internal
 *
 * @coversNothing
 */
final class NodeCrudTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->loadFixtures('basic-types', 'sample-nodes');
	}

	public function testGetNodeList(): void
	{
		$this->authenticateAs('editor');

		$response = $this->makeRequest('GET', '/api/nodes', [
			'query' => ['type' => 'test-article'],
		]);

		$this->assertResponseOk($response);
		$payload = $this->assertJsonResponse($response);
		$this->assertIsArray($payload);
	}

	public function testGetSingleNode(): void
	{
		$this->authenticateAs('editor');

		$typeId = $this->createTestType('crud-test-page');
		$nodePath = '/test/crud-test-node';
		$this->createTestNode([
			'uid' => 'crud-test-node',
			'type' => $typeId,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Test Node']],
			],
		]);
		$this->createTestPath($this->createdNodeIds[count($this->createdNodeIds) - 1], $nodePath);

		$response = $this->makeRequest('GET', '/api/node/crud-test-node');

		$payload = $this->assertJsonResponse($response);
		$this->assertSame('crud-test-node', $payload['uid'] ?? null);
		$this->assertSame('Test Node', $payload['title'] ?? null);
		$this->assertArrayHasKey('fields', $payload);
		$this->assertArrayHasKey('paths', $payload);
		$this->assertSame($nodePath, $payload['paths']['en'] ?? null);
	}

	public function testGetSingleNodeRequiresAuthentication(): void
	{
		$typeId = $this->createTestType('crud-test-page');
		$nodePath = '/test/unauth-node';
		$this->createTestNode([
			'uid' => 'crud-test-unauth-node',
			'type' => $typeId,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Unauth Node']],
			],
		]);
		$this->createTestPath($this->createdNodeIds[count($this->createdNodeIds) - 1], $nodePath);

		$response = $this->makeRequest('GET', '/api/node/crud-test-unauth-node');

		$this->assertResponseStatus(401, $response);
	}

	public function testCreateNode(): void
	{
		$this->authenticateAs('editor');

		$uid = 'new-test-node-' . uniqid();
		$this->createTestType('create-test-page');
		$nodePath = '/test/' . $uid;
		$nodeData = [
			'uid' => $uid,
			'published' => true,
			'paths' => [
				'en' => $nodePath,
			],
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'New Node']],
			],
		];

		$response = $this->makeRequest('POST', '/api/node/create-test-page', [
			'body' => $nodeData,
		]);

		$payload = $this->assertJsonResponse($response, 201);
		$this->assertTrue($payload['success'] ?? false);

		$this->trackNodeByUid($uid);

		$created = $this->makeRequest('GET', "/api/node/{$uid}");
		$createdPayload = $this->assertJsonResponse($created);
		$this->assertSame('New Node', $createdPayload['title'] ?? null);
		$this->assertSame($nodePath, $createdPayload['paths']['en'] ?? null);
	}

	public function testPreviewNodePathsUsesParentFields(): void
	{
		$this->authenticateAs('editor');

		$type = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-page'",
		)->one();
		$this->assertNotEmpty($type);

		$parentUid = 'route-preview-parent-' . uniqid();
		$childUid = 'route-preview-child-' . uniqid();

		$this->createTestNode([
			'uid' => $parentUid,
			'type' => (int) $type['type'],
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Parent Page']],
			],
		]);

		$response = $this->makeRequest('POST', '/api/node/parent-route-page/paths', [
			'body' => [
				'uid' => $childUid,
				'parent' => $parentUid,
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Child Page']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response);
		$this->assertSame('/prefix/parent-page/child-page', $payload['paths']['en'] ?? null);
		$this->assertSame('/prefix/parent-page/child-page', $payload['paths']['de'] ?? null);
	}

	public function testPreviewNodePathsSupportsTransformedCompositeParentPlaceholders(): void
	{
		$this->authenticateAs('editor');

		$parentType = $this->createTestType('route-transform-parent-' . uniqid());
		$parentUid = 'route-transform-parent-' . uniqid();
		$childUid = 'route-transform-child-' . uniqid();

		$this->createTestNode([
			'uid' => $parentUid,
			'type' => $parentType,
			'content' => [
				'countryCode' => ['type' => Text::class, 'value' => ['en' => 'DE']],
				'title' => ['type' => Text::class, 'value' => ['en' => 'Main Station']],
			],
		]);

		$response = $this->makeRequest('POST', '/api/node/transformed-route-page/paths', [
			'body' => [
				'uid' => $childUid,
				'parent' => $parentUid,
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Central Station']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response);
		$this->assertSame('/stations/de-main-station/central-station', $payload['paths']['en'] ?? null);
		$this->assertSame('/stations/de-main-station/central-station', $payload['paths']['de'] ?? null);
	}

	public function testPreviewNodePathsSupportsAncestorFields(): void
	{
		$this->authenticateAs('editor');

		$grandparentType = $this->createTestType('route-ancestor-grandparent-' . uniqid());
		$grandparentUid = 'route-ancestor-grandparent-' . uniqid();
		$grandparentId = $this->createTestNode([
			'uid' => $grandparentUid,
			'type' => $grandparentType,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Main Station']],
			],
		]);
		$parentType = $this->createTestType('route-ancestor-parent-' . uniqid());
		$parentUid = 'route-ancestor-parent-' . uniqid();
		$this->createTestNode([
			'uid' => $parentUid,
			'parent' => $grandparentId,
			'type' => $parentType,
			'content' => [
				'countryCode' => ['type' => Text::class, 'value' => ['en' => 'DE']],
				'title' => ['type' => Text::class, 'value' => ['en' => 'Central Station']],
			],
		]);

		$response = $this->makeRequest('POST', '/api/node/ancestor-field-route-page/paths', [
			'body' => [
				'uid' => 'route-ancestor-child-' . uniqid(),
				'parent' => $parentUid,
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Platform One']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response);
		$this->assertSame('/main-station/de-platform-one', $payload['paths']['en'] ?? null);
		$this->assertSame('/main-station/de-platform-one', $payload['paths']['de'] ?? null);
	}

	public function testPreviewNodePathsUsesAncestorPathShortcut(): void
	{
		$this->authenticateAs('editor');

		$grandparentType = $this->createTestType('route-ancestor-path-grandparent-' . uniqid());
		$grandparentUid = 'route-ancestor-path-grandparent-' . uniqid();
		$grandparentId = $this->createTestNode([
			'uid' => $grandparentUid,
			'type' => $grandparentType,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Main Station']],
			],
		]);
		$grandparentPath = '/stations-' . uniqid() . '/';
		$this->createTestPath($grandparentId, $grandparentPath);
		$parentType = $this->createTestType('route-ancestor-path-parent-' . uniqid());
		$parentUid = 'route-ancestor-path-parent-' . uniqid();
		$this->createTestNode([
			'uid' => $parentUid,
			'parent' => $grandparentId,
			'type' => $parentType,
			'content' => [
				'countryCode' => ['type' => Text::class, 'value' => ['en' => 'DE']],
				'title' => ['type' => Text::class, 'value' => ['en' => 'Central Station']],
			],
		]);

		$response = $this->makeRequest('POST', '/api/node/ancestor-path-route-page/paths', [
			'body' => [
				'uid' => 'route-ancestor-path-child-' . uniqid(),
				'parent' => $parentUid,
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Platform One']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response);
		$expected = rtrim($grandparentPath, '/') . '/de-platform-one';
		$this->assertSame($expected, $payload['paths']['en'] ?? null);
		$this->assertSame($expected, $payload['paths']['de'] ?? null);
	}

	public function testPreviewNodePathsUsesParentPathShortcut(): void
	{
		$this->authenticateAs('editor');

		$parentType = $this->createTestType('route-parent-path-parent-' . uniqid());
		$parentUid = 'route-parent-path-parent-' . uniqid();
		$parentId = $this->createTestNode([
			'uid' => $parentUid,
			'type' => $parentType,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Main Station']],
			],
		]);
		$parentPath = '/stations-' . uniqid() . '/';
		$this->createTestPath($parentId, $parentPath);

		$response = $this->makeRequest('POST', '/api/node/parent-path-route-page/paths', [
			'body' => [
				'uid' => 'route-parent-path-child-' . uniqid(),
				'parent' => $parentUid,
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Central Station']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response);
		$expected = rtrim($parentPath, '/') . '/central-station';
		$this->assertSame($expected, $payload['paths']['en'] ?? null);
		$this->assertSame($expected, $payload['paths']['de'] ?? null);
	}

	public function testPreviewNodePathsKeepsMissingParentPathShortcut(): void
	{
		$this->authenticateAs('editor');

		$parentType = $this->createTestType('route-parent-path-missing-parent-' . uniqid());
		$parentUid = 'route-parent-path-missing-parent-' . uniqid();
		$this->createTestNode([
			'uid' => $parentUid,
			'type' => $parentType,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Main Station']],
			],
		]);

		$response = $this->makeRequest('POST', '/api/node/parent-path-route-page/paths', [
			'body' => [
				'uid' => 'route-parent-path-missing-child',
				'parent' => $parentUid,
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Central Station']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response, 200);
		$this->assertSame('/[parent path]/central-station', $payload['paths']['en'] ?? null);
		$this->assertSame('/[parent path]/central-station', $payload['paths']['de'] ?? null);
	}

	public function testPreviewNodePathsKeepsMissingAncestorPlaceholder(): void
	{
		$this->authenticateAs('editor');

		$parentType = $this->createTestType('route-missing-ancestor-parent-' . uniqid());
		$parentUid = 'route-missing-ancestor-parent-' . uniqid();
		$this->createTestNode([
			'uid' => $parentUid,
			'type' => $parentType,
			'content' => [
				'countryCode' => ['type' => Text::class, 'value' => ['en' => 'DE']],
				'title' => ['type' => Text::class, 'value' => ['en' => 'Central Station']],
			],
		]);

		$response = $this->makeRequest('POST', '/api/node/ancestor-field-route-page/paths', [
			'body' => [
				'uid' => 'route-missing-ancestor-child-' . uniqid(),
				'parent' => $parentUid,
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Platform One']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response, 200);
		$this->assertSame('/[ancestor title]/de-platform-one', $payload['paths']['en'] ?? null);
		$this->assertSame('/[ancestor title]/de-platform-one', $payload['paths']['de'] ?? null);
	}

	public function testPreviewNodePathsKeepsMissingParentPlaceholder(): void
	{
		$this->authenticateAs('editor');

		$response = $this->makeRequest('POST', '/api/node/parent-route-page/paths', [
			'body' => [
				'uid' => 'route-preview-child',
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Child Page']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response, 200);
		$this->assertSame('/prefix/[parent title]/child-page', $payload['paths']['en'] ?? null);
		$this->assertSame('/prefix/[parent title]/child-page', $payload['paths']['de'] ?? null);
	}

	public function testPreviewNodePathsKeepsMissingFieldPlaceholder(): void
	{
		$this->authenticateAs('editor');

		$type = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-page'",
		)->one();
		$this->assertNotEmpty($type);

		$parentUid = 'route-preview-missing-title-parent-' . uniqid();

		$this->createTestNode([
			'uid' => $parentUid,
			'type' => (int) $type['type'],
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Parent Page']],
			],
		]);

		$response = $this->makeRequest('POST', '/api/node/parent-route-page/paths', [
			'body' => [
				'uid' => 'route-preview-missing-title-child',
				'parent' => $parentUid,
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => null, 'de' => null]],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response, 200);
		$this->assertSame('/prefix/parent-page/[title]', $payload['paths']['en'] ?? null);
		$this->assertSame('/prefix/parent-page/[title]', $payload['paths']['de'] ?? null);
	}

	public function testCreateNodeGeneratesRoutePathFromParentFieldsOnServer(): void
	{
		$this->authenticateAs('editor');

		$this->createTestType('parent-route-page');
		$type = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-page'",
		)->one();
		$this->assertNotEmpty($type);

		$parentUid = 'route-save-parent-' . uniqid();

		$this->createTestNode([
			'uid' => $parentUid,
			'type' => (int) $type['type'],
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Parent Page']],
			],
		]);

		$childUid = 'route-save-child-' . uniqid();
		$response = $this->makeRequest('POST', '/api/node/parent-route-page', [
			'body' => [
				'uid' => $childUid,
				'parent' => $parentUid,
				'published' => true,
				'hidden' => false,
				'locked' => false,
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Child Page']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response, 201);
		$this->assertTrue($payload['success'] ?? false);
		$this->trackNodeByUid($childUid);

		$created = $this->makeRequest('GET', "/api/node/{$childUid}");
		$createdPayload = $this->assertJsonResponse($created);
		$this->assertSame('/prefix/parent-page/child-page', $createdPayload['paths']['en'] ?? null);
		$this->assertSame($parentUid, $createdPayload['parent'] ?? null);
	}

	public function testCreateNodeGeneratesRoutePathFromParentPathOnServer(): void
	{
		$this->authenticateAs('editor');

		$this->createTestType('parent-path-route-page');
		$parentType = $this->createTestType('route-save-parent-path-parent-' . uniqid());
		$parentUid = 'route-save-parent-path-parent-' . uniqid();
		$parentId = $this->createTestNode([
			'uid' => $parentUid,
			'type' => $parentType,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Main Station']],
			],
		]);
		$parentPath = '/stations-save-' . uniqid() . '/';
		$this->createTestPath($parentId, $parentPath);

		$childUid = 'route-save-parent-path-child-' . uniqid();
		$response = $this->makeRequest('POST', '/api/node/parent-path-route-page', [
			'body' => [
				'uid' => $childUid,
				'parent' => $parentUid,
				'published' => true,
				'hidden' => false,
				'locked' => false,
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Central Station']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response, 201);
		$this->assertTrue($payload['success'] ?? false);
		$this->trackNodeByUid($childUid);

		$created = $this->makeRequest('GET', "/api/node/{$childUid}");
		$createdPayload = $this->assertJsonResponse($created);
		$this->assertSame(
			rtrim($parentPath, '/') . '/central-station',
			$createdPayload['paths']['en'] ?? null,
		);
	}

	public function testCreateNodeGeneratesRoutePathFromAncestorPathOnServer(): void
	{
		$this->authenticateAs('editor');

		$this->createTestType('ancestor-path-route-page');
		$grandparentType = $this->createTestType('route-save-ancestor-path-grandparent-' . uniqid());
		$grandparentUid = 'route-save-ancestor-path-grandparent-' . uniqid();
		$grandparentId = $this->createTestNode([
			'uid' => $grandparentUid,
			'type' => $grandparentType,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Main Station']],
			],
		]);
		$grandparentPath = '/stations-save-' . uniqid() . '/';
		$this->createTestPath($grandparentId, $grandparentPath);
		$parentType = $this->createTestType('route-save-ancestor-path-parent-' . uniqid());
		$parentUid = 'route-save-ancestor-path-parent-' . uniqid();
		$this->createTestNode([
			'uid' => $parentUid,
			'parent' => $grandparentId,
			'type' => $parentType,
			'content' => [
				'countryCode' => ['type' => Text::class, 'value' => ['en' => 'DE']],
				'title' => ['type' => Text::class, 'value' => ['en' => 'Central Station']],
			],
		]);

		$childUid = 'route-save-ancestor-path-child-' . uniqid();
		$response = $this->makeRequest('POST', '/api/node/ancestor-path-route-page', [
			'body' => [
				'uid' => $childUid,
				'parent' => $parentUid,
				'published' => true,
				'hidden' => false,
				'locked' => false,
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Platform One']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response, 201);
		$this->assertTrue($payload['success'] ?? false);
		$this->trackNodeByUid($childUid);

		$created = $this->makeRequest('GET', "/api/node/{$childUid}");
		$createdPayload = $this->assertJsonResponse($created);
		$this->assertSame(
			rtrim($grandparentPath, '/') . '/de-platform-one',
			$createdPayload['paths']['en'] ?? null,
		);
	}

	public function testCreateNodeRejectsMissingParentPathShortcut(): void
	{
		$this->authenticateAs('editor');

		$this->createTestType('parent-path-route-page');
		$parentType = $this->createTestType('route-save-missing-parent-path-parent-' . uniqid());
		$parentUid = 'route-save-missing-parent-path-parent-' . uniqid();
		$this->createTestNode([
			'uid' => $parentUid,
			'type' => $parentType,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Main Station']],
			],
		]);

		$response = $this->makeRequest('POST', '/api/node/parent-path-route-page', [
			'headers' => ['Accept' => 'application/json'],
			'body' => [
				'uid' => 'route-save-missing-parent-path-child',
				'parent' => $parentUid,
				'published' => true,
				'hidden' => false,
				'locked' => false,
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Central Station']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response, 400);
		$this->assertSame('Could not resolve route placeholder: {parent}', $payload['message'] ?? null);
	}

	public function testCreateNodeRejectsMissingGeneratedRoutePlaceholder(): void
	{
		$this->authenticateAs('editor');

		$this->createTestType('parent-route-page');
		$type = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-page'",
		)->one();
		$this->assertNotEmpty($type);

		$parentUid = 'route-save-missing-title-parent-' . uniqid();

		$this->createTestNode([
			'uid' => $parentUid,
			'type' => (int) $type['type'],
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Parent Page']],
			],
		]);

		$response = $this->makeRequest('POST', '/api/node/parent-route-page', [
			'headers' => ['Accept' => 'application/json'],
			'body' => [
				'uid' => 'route-save-missing-title-child',
				'parent' => $parentUid,
				'published' => true,
				'hidden' => false,
				'locked' => false,
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => null, 'de' => null]],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response, 400);
		$this->assertSame('Could not resolve route placeholder: {title}', $payload['message'] ?? null);
	}

	public function testCreateNodePersistsHandle(): void
	{
		$this->authenticateAs('editor');

		$uid = 'handled-create-node-' . uniqid();
		$handle = 'handled-create-' . uniqid();
		$nodePath = '/test/' . $uid;
		$response = $this->makeRequest('POST', '/api/node/test-page', [
			'body' => [
				'uid' => $uid,
				'handle' => $handle,
				'published' => true,
				'hidden' => false,
				'locked' => false,
				'paths' => [
					'en' => $nodePath,
				],
				'generatedPaths' => [],
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Handled Node']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response, 201);
		$this->assertTrue($payload['success'] ?? false);
		$this->trackNodeByUid($uid);

		$created = $this->makeRequest('GET', "/api/node/{$uid}");
		$createdPayload = $this->assertJsonResponse($created);
		$this->assertSame($handle, $createdPayload['handle'] ?? null);
	}

	public function testCreateNodeRejectsDuplicateUidWithoutUpdatingExisting(): void
	{
		$this->authenticateAs('editor');

		$type = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-page'",
		)->one();
		$this->assertNotEmpty($type);

		$uid = 'duplicate-create-node-' . uniqid();
		$this->createTestNode([
			'uid' => $uid,
			'type' => (int) $type['type'],
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Original Title']],
			],
		]);

		$response = $this->makeRequest('POST', '/api/node/test-page', [
			'body' => [
				'uid' => $uid,
				'published' => true,
				'hidden' => false,
				'locked' => false,
				'paths' => [
					'en' => '/test/' . $uid,
				],
				'generatedPaths' => [],
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Changed Title']],
				],
			],
		]);

		$this->assertResponseStatus(409, $response);

		$stored = $this->db()->execute(
			"SELECT content->'title'->'value'->>'en' AS title FROM cms.nodes WHERE uid = :uid",
			['uid' => $uid],
		)->one();
		$this->assertSame('Original Title', $stored['title'] ?? null);
	}

	public function testCreateNodeGeneratesMissingUid(): void
	{
		$this->authenticateAs('editor');

		$path = '/test/generated-uid-' . uniqid();
		$response = $this->makeRequest('POST', '/api/node/test-page', [
			'body' => [
				'published' => true,
				'hidden' => false,
				'locked' => false,
				'paths' => [
					'en' => $path,
				],
				'generatedPaths' => [],
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Generated UID']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response, 201);
		$this->assertTrue($payload['success'] ?? false);
		$this->assertIsString($payload['uid'] ?? null);
		$alphabet = preg_quote(Uid::ALPHABET_LOWERCASE_WORD_SAFE, '/');
		$this->assertMatchesRegularExpression("/^[{$alphabet}]{13}$/", $payload['uid']);

		$this->trackNodeByUid($payload['uid']);

		$stored = $this->db()->execute(
			"SELECT content->'title'->'value'->>'en' AS title FROM cms.nodes WHERE uid = :uid",
			['uid' => $payload['uid']],
		)->one();
		$this->assertSame('Generated UID', $stored['title'] ?? null);
	}

	public function testCreateNodeUsesConfiguredUid(): void
	{
		$this->app = $this->createApp([
			'uid.alphabet' => 'abc',
			'uid.length' => 8,
		]);
		$this->authenticateAs('editor');

		$path = '/test/configured-uid-' . uniqid();
		$response = $this->makeRequest('POST', '/api/node/test-page', [
			'body' => [
				'published' => true,
				'hidden' => false,
				'locked' => false,
				'paths' => [
					'en' => $path,
				],
				'generatedPaths' => [],
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Configured UID']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response, 201);
		$this->assertTrue($payload['success'] ?? false);
		$this->assertMatchesRegularExpression('/^[abc]{8}$/', $payload['uid'] ?? '');

		$this->trackNodeByUid($payload['uid']);
	}

	public function testCreateNodePersistsParentUid(): void
	{
		$this->authenticateAs('editor');

		$type = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-page'",
		)->one();
		$this->assertNotEmpty($type);

		$this->createTestNode([
			'uid' => 'parent-for-child-create',
			'type' => (int) $type['type'],
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Parent Node']],
			],
		]);

		$uid = 'child-with-parent-' . uniqid();
		$nodePath = '/test/' . $uid;
		$nodeData = [
			'uid' => $uid,
			'parent' => 'parent-for-child-create',
			'published' => true,
			'hidden' => false,
			'locked' => false,
			'paths' => [
				'en' => $nodePath,
			],
			'generatedPaths' => [],
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Child Node']],
			],
		];

		$response = $this->makeRequest('POST', '/api/node/test-page', [
			'body' => $nodeData,
		]);

		$payload = $this->assertJsonResponse($response, 201);
		$this->assertTrue($payload['success'] ?? false);

		$this->trackNodeByUid($uid);

		$stored = $this->db()->execute(
			'SELECT c.parent, p.uid AS parent_uid FROM cms.nodes c LEFT JOIN cms.nodes p ON p.node = c.parent WHERE c.uid = :uid',
			['uid' => $uid],
		)->one();

		$this->assertNotNull($stored['parent'] ?? null);
		$this->assertSame('parent-for-child-create', $stored['parent_uid'] ?? null);
	}

	public function testCreateNodeRejectsInvalidParentUid(): void
	{
		$this->authenticateAs('editor');

		$uid = 'child-invalid-parent-' . uniqid();
		$nodeData = [
			'uid' => $uid,
			'parent' => 'missing-parent',
			'published' => true,
			'hidden' => false,
			'locked' => false,
			'paths' => [
				'en' => '/test/' . $uid,
			],
			'generatedPaths' => [],
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Child Node']],
			],
		];

		$response = $this->makeRequest('POST', '/api/node/test-page', [
			'body' => $nodeData,
		]);

		$this->assertResponseStatus(400, $response);

		$node = $this->db()->execute('SELECT node FROM cms.nodes WHERE uid = :uid', [
			'uid' => $uid,
		])->first();
		$this->assertNull($node);
	}

	public function testUpdateNode(): void
	{
		$this->authenticateAs('editor');

		$typeId = $this->createTestType('update-test-page');
		$uid = 'update-test-node-' . uniqid();
		$this->createTestNode([
			'uid' => $uid,
			'type' => $typeId,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Original Title']],
			],
		]);
		$this->createTestPath($this->createdNodeIds[count($this->createdNodeIds) - 1], '/test/' . $uid);

		$updateData = [
			'uid' => $uid,
			'published' => true,
			'locked' => false,
			'hidden' => false,
			'paths' => [
				'en' => '/test/' . $uid,
			],
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Updated Title']],
			],
		];

		$response = $this->makeRequest('PUT', "/api/node/{$uid}", [
			'body' => $updateData,
		]);

		$payload = $this->assertJsonResponse($response);
		$this->assertTrue($payload['success'] ?? false);
		$this->assertSame($uid, $payload['uid'] ?? null);

		$reloaded = $this->makeRequest('GET', "/api/node/{$uid}");
		$reloadedPayload = $this->assertJsonResponse($reloaded);
		$this->assertSame('Updated Title', $reloadedPayload['title'] ?? null);
	}

	public function testUpdateNodeRejectsUidChange(): void
	{
		$this->authenticateAs('editor');

		$typeId = $this->createTestType('update-test-page');
		$uid = 'reject-uid-change-' . uniqid();
		$nodeId = $this->createTestNode([
			'uid' => $uid,
			'type' => $typeId,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Original Title']],
			],
		]);
		$this->createTestPath($nodeId, '/test/' . $uid);

		$response = $this->makeRequest('PUT', "/api/node/{$uid}", [
			'body' => [
				'uid' => $uid . '-changed',
				'published' => true,
				'locked' => false,
				'hidden' => false,
				'paths' => [
					'en' => '/test/' . $uid,
				],
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Changed Title']],
				],
			],
		]);

		$this->assertResponseStatus(400, $response);

		$stored = $this->db()->execute(
			"SELECT uid, content->'title'->'value'->>'en' AS title FROM cms.nodes WHERE node = :node",
			['node' => $nodeId],
		)->one();
		$this->assertSame($uid, $stored['uid'] ?? null);
		$this->assertSame('Original Title', $stored['title'] ?? null);
	}

	public function testUpdateNodeCanRemoveHandle(): void
	{
		$this->authenticateAs('editor');

		$typeId = $this->createTestType('update-test-page');
		$uid = 'update-handle-node-' . uniqid();
		$nodeId = $this->createTestNode([
			'uid' => $uid,
			'handle' => 'removed-handle-' . uniqid(),
			'type' => $typeId,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Handled Update']],
			],
		]);
		$this->createTestPath($nodeId, '/test/' . $uid);

		$response = $this->makeRequest('PUT', "/api/node/{$uid}", [
			'body' => [
				'uid' => $uid,
				'handle' => '',
				'published' => true,
				'locked' => false,
				'hidden' => false,
				'paths' => [
					'en' => '/test/' . $uid,
				],
				'content' => [
					'title' => ['type' => Text::class, 'value' => ['en' => 'Handled Update']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response);
		$this->assertTrue($payload['success'] ?? false);

		$handle = $this->db()->execute(
			'SELECT handle FROM cms.node_handles WHERE node = :node',
			['node' => $nodeId],
		)->first();
		$this->assertNull($handle);
	}

	public function testDeleteNode(): void
	{
		$this->authenticateAs('editor');

		$typeId = $this->createTestType('delete-test-page-' . uniqid());
		$uid = 'delete-test-node-' . uniqid();
		$this->createTestNode([
			'uid' => $uid,
			'type' => $typeId,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Delete Node']],
			],
		]);
		$this->createTestPath($this->createdNodeIds[count($this->createdNodeIds) - 1], '/test/' . $uid);

		$response = $this->makeRequest('DELETE', "/api/node/{$uid}", [
			'headers' => ['Accept' => 'application/json'],
		]);

		$this->assertResponseStatus(500, $response);
	}
}
