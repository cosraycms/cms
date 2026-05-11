<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\End2End;

use Celemas\Cms\Tests\End2EndTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class InactivePathRedirectTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->loadFixtures('basic-types');
	}

	public function testInactivePathRedirectsToActivePath(): void
	{
		$this->authenticateAs('editor');

		$typeHandle = 'inactive-path-page-' . uniqid();
		$typeId = $this->createTestType($typeHandle);
		$uid = 'inactive-path-node-' . uniqid();
		$this->createTestNode([
			'uid' => $uid,
			'type' => $typeId,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Inactive Path Node']],
			],
		]);
		$nodeId = $this->createdNodeIds[count($this->createdNodeIds) - 1];
		$initialPath = '/test/inactive-path-initial-' . uniqid();
		$updatedPath = '/test/inactive-path-updated-' . uniqid();
		$this->createTestPath($nodeId, $initialPath);

		$updateData = [
			'uid' => $uid,
			'published' => true,
			'locked' => false,
			'hidden' => false,
			'paths' => [
				'en' => $updatedPath,
			],
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Inactive Path Node']],
			],
		];

		$db = $this->db();
		$oldPathRow = $db->execute(
			'SELECT path, locale FROM cms.urlpaths WHERE node = :node ORDER BY created DESC LIMIT 1',
			['node' => $nodeId],
		)->one();
		$this->assertNotEmpty($oldPathRow);
		$db->execute(
			'UPDATE cms.urlpaths SET inactive = now(), editor = :editor WHERE path = :path AND locale = :locale',
			[
				'editor' => 1,
				'path' => $oldPathRow['path'],
				'locale' => $oldPathRow['locale'],
			],
		)->run();
		$db->execute(
			'INSERT INTO cms.urlpaths (node, path, locale, creator, editor) VALUES (:node, :path, :locale, :creator, :editor)',
			[
				'node' => $nodeId,
				'path' => $updatedPath,
				'locale' => 'en',
				'creator' => 1,
				'editor' => 1,
			],
		)->run();

		$pathRow = $db->execute(
			'SELECT path, inactive FROM cms.urlpaths WHERE path = :path',
			['path' => $initialPath],
		)->one();
		$this->assertNotEmpty($pathRow);
		$this->assertNotNull($pathRow['inactive']);
		$activePathRow = $db->execute(
			'SELECT path FROM cms.urlpaths WHERE node = :node AND inactive IS NULL',
			['node' => $nodeId],
		)->one();
		$this->assertSame($updatedPath, $activePathRow['path'] ?? null);
	}
}
