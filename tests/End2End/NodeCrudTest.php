<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;

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
				'title' => ['type' => 'text', 'value' => ['en' => 'Test Node']],
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
				'title' => ['type' => 'text', 'value' => ['en' => 'Unauth Node']],
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
				'title' => ['type' => 'text', 'value' => ['en' => 'New Node']],
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
				'title' => ['type' => 'text', 'value' => ['en' => 'Original Title']],
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
					'title' => ['type' => 'text', 'value' => ['en' => 'Changed Title']],
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
					'title' => ['type' => 'text', 'value' => ['en' => 'Generated UID']],
				],
			],
		]);

		$payload = $this->assertJsonResponse($response, 201);
		$this->assertTrue($payload['success'] ?? false);
		$this->assertIsString($payload['uid'] ?? null);
		$this->assertMatchesRegularExpression('/^[123456789bcdfghklmnpqrstvwxyz]{13}$/', $payload['uid']);

		$this->trackNodeByUid($payload['uid']);

		$stored = $this->db()->execute(
			"SELECT content->'title'->'value'->>'en' AS title FROM cms.nodes WHERE uid = :uid",
			['uid' => $payload['uid']],
		)->one();
		$this->assertSame('Generated UID', $stored['title'] ?? null);
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
				'title' => ['type' => 'text', 'value' => ['en' => 'Parent Node']],
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
				'title' => ['type' => 'text', 'value' => ['en' => 'Child Node']],
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
				'title' => ['type' => 'text', 'value' => ['en' => 'Child Node']],
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
				'title' => ['type' => 'text', 'value' => ['en' => 'Original Title']],
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
				'title' => ['type' => 'text', 'value' => ['en' => 'Updated Title']],
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

	public function testDeleteNode(): void
	{
		$this->authenticateAs('editor');

		$typeId = $this->createTestType('delete-test-page-' . uniqid());
		$uid = 'delete-test-node-' . uniqid();
		$this->createTestNode([
			'uid' => $uid,
			'type' => $typeId,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Delete Node']],
			],
		]);
		$this->createTestPath($this->createdNodeIds[count($this->createdNodeIds) - 1], '/test/' . $uid);

		$response = $this->makeRequest('DELETE', "/api/node/{$uid}", [
			'headers' => ['Accept' => 'application/json'],
		]);

		$this->assertResponseStatus(500, $response);
	}
}
