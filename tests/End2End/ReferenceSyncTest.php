<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Field\Blocks;
use Cosray\Field\Image;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;

/**
 * Saving through the panel keeps the reference indexes in step with
 * the stored content (Store::persist syncs inside the transaction).
 *
 * @internal
 *
 * @coversNothing
 */
final class ReferenceSyncTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
		$this->authenticateAs('editor');
	}

	protected function tearDown(): void
	{
		foreach (['asset_references', 'node_references'] as $table) {
			$this->db()->execute(
				"DELETE FROM cms.{$table} WHERE owner_uid LIKE 'e2e-refsync-%'",
			)->run();
		}

		$this->db()->execute(
			"DELETE FROM cms.assets WHERE uid LIKE 'e2e-refsync-%'",
		)->run();
		parent::tearDown();
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$plugin = parent::createBootstrap($config);
		$plugin->section('Inhalt')->collection(TestArticlesCollection::class);

		return $plugin;
	}

	public function testPanelSaveSyncsReferenceRows(): void
	{
		$this->db()->execute(
			"INSERT INTO cms.assets (uid, disk, key, filename, kind, creator)
			VALUES ('e2e-refsync-img', 'local', 'e2/e2e-refsync-img/pic.png', 'pic.png', 'image', 1)",
		)->run();

		$mediaType = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-media-document'",
		)->first();
		$typeId = $mediaType
			? (int) $mediaType['type']
			: $this->createTestType('test-media-document');

		$this->createTestNode(['uid' => 'e2e-refsync-target', 'type' => $typeId]);
		$this->createTestNode([
			'uid' => 'e2e-refsync-node',
			'type' => $typeId,
			'published' => true,
			// Pre-encoded to bypass createTestNode's legacy content
			// normalization, which rewrites {uid} items to {file}.
			'content' => json_encode([
				'gallery' => [
					'type' => Image::class,
					'value' => ['zxx' => [['uid' => 'e2e-refsync-img']]],
				],
				'contentBlocks' => [
					'type' => Blocks::class,
					'value' => [
						'zxx' => [[
							'type' => 'richtext',
							'colspan' => 12,
							'format' => 'cosray-richtext',
							'version' => 1,
							'value' => [
								'zxx' => [
									'type' => 'doc',
									'content' => [[
										'type' => 'paragraph',
										'content' => [[
											'type' => 'text',
											'text' => 'Interner Link',
											'marks' => [[
												'type' => 'link',
												'attrs' => ['node' => 'e2e-refsync-target'],
											]],
										]],
									]],
								],
							],
						]],
					],
				],
			]),
		]);

		$response = $this->makeRequest('POST', '/cp/collection/test-articles/e2e-refsync-node', [
			'headers' => ['HX-Request' => 'true'],
			'body' => [
				'content' => [
					'category' => ['value' => ['zxx' => 'news']],
				],
			],
		]);

		$this->assertResponseOk($response);

		$assetRefs = $this->db()->execute(
			"SELECT asset_uid FROM cms.asset_references
			WHERE owner_type = 'node' AND owner_uid = 'e2e-refsync-node'",
		)->all();
		$nodeRefs = $this->db()->execute(
			"SELECT target_uid FROM cms.node_references
			WHERE owner_type = 'node' AND owner_uid = 'e2e-refsync-node'",
		)->all();

		$this->assertSame([['asset_uid' => 'e2e-refsync-img']], $assetRefs);
		$this->assertSame([['target_uid' => 'e2e-refsync-target']], $nodeRefs);
	}
}
