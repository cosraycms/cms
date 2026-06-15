<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Field\Code;
use Cosray\Tests\IntegrationTestCase;

final class NodeTest extends IntegrationTestCase
{
	public function testCreateAndRetrieveNode(): void
	{
		$typeId = $this->createTestType('integration-test-page');
		$content = [
			'title' => [
				'type' => 'text',
				'value' => ['de' => 'Testseite', 'en' => 'Test Page'],
			],
			'body' => [
				'type' => 'richtext',
				'value' => ['de' => '<p>Deutscher Inhalt</p>', 'en' => '<p>English content</p>'],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'integration-test-node-1',
			'type' => $typeId,
			'content' => $content,
		]);

		$node = $this->db()->execute(
			'SELECT * FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$this->assertNotNull($node);
		$this->assertEquals('integration-test-node-1', $node['uid']);
		$this->assertEquals($typeId, $node['type']);
		$this->assertTrue($node['published']);
		$this->assertFalse($node['hidden']);

		$contentData = json_decode($node['content'], true);
		$this->assertEquals('Testseite', $contentData['title']['value']['de']);
		$this->assertEquals('Test Page', $contentData['title']['value']['en']);
	}

	public function testCreateNodeWithDefaults(): void
	{
		$typeId = $this->createTestType('default-test-page');

		$nodeId = $this->createTestNode([
			'type' => $typeId,
		]);

		$node = $this->db()->execute(
			'SELECT * FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$this->assertNotNull($node);
		$this->assertTrue($node['published']); // Default is true
		$this->assertFalse($node['hidden']); // Default is false
		$this->assertFalse($node['locked']); // Default is false
		$this->assertEquals(1, $node['creator']); // System user
		$this->assertEquals(1, $node['editor']); // System user
	}

	public function testNodeContentPersistsCodeSyntaxAlongsideValue(): void
	{
		$typeId = $this->createTestType('code-test-page');
		$content = [
			'code' => [
				'type' => Code::class,
				'meta' => ['syntax' => ['zxx' => 'php']],
				'value' => ['de' => '<?php echo "Hallo";', 'en' => '<?php echo "Hello";'],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'code-test-node-1',
			'type' => $typeId,
			'content' => $content,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$contentData = json_decode($node['content'], true);
		$this->assertSame(Code::class, $contentData['code']['type']);
		$this->assertSame('php', $contentData['code']['meta']['syntax']['zxx']);
		$this->assertSame('<?php echo "Hallo";', $contentData['code']['value']['de']);
		$this->assertSame('<?php echo "Hello";', $contentData['code']['value']['en']);
	}

	public function testUpdateNodeContent(): void
	{
		$typeId = $this->createTestType('update-test-page');
		$initialContent = [
			'title' => ['type' => 'text', 'value' => ['en' => 'Initial Title']],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'update-test-node',
			'type' => $typeId,
			'content' => $initialContent,
		]);

		$updatedContent = [
			'title' => ['type' => 'text', 'value' => ['en' => 'Updated Title']],
			'subtitle' => ['type' => 'text', 'value' => ['en' => 'New Subtitle']],
		];

		$this->db()->execute(
			'UPDATE cms.nodes SET content = :content::jsonb WHERE node = :id',
			['id' => $nodeId, 'content' => json_encode($updatedContent)],
		)->run();

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$contentData = json_decode($node['content'], true);
		$this->assertEquals('Updated Title', $contentData['title']['value']['en']);
		$this->assertEquals('New Subtitle', $contentData['subtitle']['value']['en']);
	}

	public function testNodeUidCannotBeUpdated(): void
	{
		$typeId = $this->createTestType('immutable-uid-test-page');
		$nodeId = $this->createTestNode([
			'uid' => 'immutable-uid-node',
			'type' => $typeId,
		]);

		$this->expectException(\Throwable::class);

		$this->db()->execute(
			'UPDATE cms.nodes SET uid = :uid WHERE node = :node',
			['uid' => 'changed-immutable-uid-node', 'node' => $nodeId],
		)->run();
	}

	public function testQueryNodesByType(): void
	{
		$typeId = $this->createTestType('query-test-page');

		$this->createTestNode(['uid' => 'query-node-1', 'type' => $typeId, 'published' => true]);
		$this->createTestNode(['uid' => 'query-node-2', 'type' => $typeId, 'published' => true]);
		$this->createTestNode(['uid' => 'query-node-3', 'type' => $typeId, 'published' => false]);

		$nodes = $this->db()->execute(
			'SELECT * FROM cms.nodes WHERE type = :type AND published = true ORDER BY node',
			['type' => $typeId],
		)->all();

		$this->assertCount(2, $nodes);
		$this->assertEquals('query-node-1', $nodes[0]['uid']);
		$this->assertEquals('query-node-2', $nodes[1]['uid']);
	}

	public function testNodeHierarchy(): void
	{
		$typeId = $this->createTestType('hierarchy-test-page');

		$parentId = $this->createTestNode([
			'uid' => 'hierarchy-parent',
			'type' => $typeId,
		]);

		$childId = $this->createTestNode([
			'uid' => 'hierarchy-child',
			'type' => $typeId,
			'parent' => $parentId,
		]);

		$children = $this->db()->execute(
			'SELECT * FROM cms.nodes WHERE parent = :parent',
			['parent' => $parentId],
		)->all();

		$this->assertCount(1, $children);
		$this->assertEquals('hierarchy-child', $children[0]['uid']);
		$this->assertEquals($parentId, $children[0]['parent']);
	}

	public function testDeleteNode(): void
	{
		$typeId = $this->createTestType('delete-test-page');
		$nodeId = $this->createTestNode([
			'uid' => 'delete-test-node',
			'type' => $typeId,
		]);

		$exists = $this->db()->execute(
			'SELECT EXISTS(SELECT 1 FROM cms.nodes WHERE node = :id) as exists',
			['id' => $nodeId],
		)->one()['exists'];
		$this->assertTrue($exists);

		$this->db()->execute(
			'DELETE FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->run();

		$exists = $this->db()->execute(
			'SELECT EXISTS(SELECT 1 FROM cms.nodes WHERE node = :id) as exists',
			['id' => $nodeId],
		)->one()['exists'];
		$this->assertFalse($exists);
	}

	public function testNodeJsonbQuerying(): void
	{
		$typeId = $this->createTestType('jsonb-test-page');

		$this->createTestNode([
			'uid' => 'jsonb-node-1',
			'type' => $typeId,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'First Title']],
			],
		]);

		$this->createTestNode([
			'uid' => 'jsonb-node-2',
			'type' => $typeId,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Second Title']],
			],
		]);

		$nodes = $this->db()->execute(
			"SELECT uid, content->'title'->'value'->>'en' as title
			 FROM cms.nodes
			 WHERE type = :type
			 AND content->'title'->'value'->>'en' LIKE '%Second%'",
			['type' => $typeId],
		)->all();

		$this->assertCount(1, $nodes);
		$this->assertEquals('jsonb-node-2', $nodes[0]['uid']);
		$this->assertEquals('Second Title', $nodes[0]['title']);
	}

	public function testCreateTestUserMatchesSchema(): void
	{
		$userId = $this->createTestUser([
			'uid' => 'integration-test-user',
			'username' => 'integration-user',
			'email' => 'integration-user@example.com',
			'rolename' => 'admin',
			'data' => ['name' => 'Integration User'],
		]);

		$user = $this->db()->execute(
			'SELECT uid, username, email, rolename, active, data FROM cms.users WHERE usr = :usr',
			['usr' => $userId],
		)->one();

		$this->assertNotNull($user);
		$this->assertSame('integration-test-user', $user['uid']);
		$this->assertSame('integration-user', $user['username']);
		$this->assertSame('integration-user@example.com', $user['email']);
		$this->assertSame('admin', $user['rolename']);
		$this->assertTrue($user['active']);
		$data = json_decode($user['data'], true);
		$this->assertSame('Integration User', $data['name'] ?? null);
	}

	public function testPagePathRequiresDefaultLocale(): void
	{
		$pathManager = new \Cosray\Node\PathManager();
		$context = $this->createContext();
		$db = $this->testDb;
		$locales = $context->locales();

		$typeId = $this->createTestType('path-locale-test');
		$nodeId = $this->createTestNode([
			'uid' => 'path-missing-default',
			'type' => $typeId,
			'content' => [],
		]);

		$this->throws(
			\Cosray\Exception\RuntimeException::class,
			'Hauptsprache',
		);

		$pathManager->persist(
			$db,
			[
				'paths' => ['de' => '/nur-de'],
				'generatedPaths' => [],
			],
			1,
			$nodeId,
			$locales,
		);
	}
}
