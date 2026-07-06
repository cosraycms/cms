<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\References\Rebuild;
use Cosray\References\Sync;
use Cosray\References\Usage;
use Cosray\Tests\IntegrationTestCase;
use PDOException;

/**
 * @internal
 *
 * @coversNothing
 */
final class ReferencesTest extends IntegrationTestCase
{
	public function testReplaceInsertsRowsAndSkipsDanglingUids(): void
	{
		$this->insertAsset('refint-asset-a');
		$this->insertAsset('refint-asset-b');
		$typeId = $this->createTestType('refint-type');
		$this->createTestNode(['uid' => 'refint-target', 'type' => $typeId]);

		$sync = new Sync($this->db());
		$sync->replace('node', 'refint-owner', [
			'assets' => ['refint-asset-a', 'refint-asset-b', 'refint-dangling'],
			'nodes' => ['refint-target', 'refint-ghost'],
		]);

		$this->assertSame(['refint-asset-a', 'refint-asset-b'], $this->assetRefs('refint-owner'));
		$this->assertSame(['refint-target'], $this->nodeRefs('refint-owner'));

		// Full replace: a second sync drops rows that vanished from content.
		$sync->replace('node', 'refint-owner', [
			'assets' => ['refint-asset-b'],
			'nodes' => [],
		]);

		$this->assertSame(['refint-asset-b'], $this->assetRefs('refint-owner'));
		$this->assertSame([], $this->nodeRefs('refint-owner'));

		$sync->remove('node', 'refint-owner');

		$this->assertSame([], $this->assetRefs('refint-owner'));
	}

	public function testNodeDeleteScriptRemovesReferenceRows(): void
	{
		$this->insertAsset('refint-del-asset');
		$typeId = $this->createTestType('refint-del-type');
		$this->createTestNode(['uid' => 'refint-del-node', 'type' => $typeId]);

		new Sync($this->db())->replace('node', 'refint-del-node', [
			'assets' => ['refint-del-asset'],
			'nodes' => [],
		]);

		$this->assertSame(['refint-del-asset'], $this->assetRefs('refint-del-node'));

		$this->db()->nodes->delete(['uid' => 'refint-del-node', 'editor' => 1])->run();

		$this->assertSame([], $this->assetRefs('refint-del-node'));
		$this->assertNotNull(
			$this->db()->execute(
				'SELECT deleted FROM cms.nodes WHERE uid = :uid',
				['uid' => 'refint-del-node'],
			)->one()['deleted'],
		);
	}

	public function testRestrictBlocksDeletingReferencedAssets(): void
	{
		$this->insertAsset('refint-locked');
		new Sync($this->db())->replace('node', 'refint-lock-owner', [
			'assets' => ['refint-locked'],
			'nodes' => [],
		]);

		try {
			$this->db()->assets->delete(['uid' => 'refint-locked'])->run();
			$this->fail('Expected a foreign key violation');
		} catch (PDOException $e) {
			// Depending on the Postgres version a RESTRICT block reports either
			// restrict_violation (23001) or foreign_key_violation (23503); the
			// delete guard accepts both, so the test does too.
			$this->assertContains((string) $e->getCode(), ['23001', '23503']);
		}
	}

	public function testUsageEnrichesOwnersForDisplay(): void
	{
		$this->insertAsset('refint-used');
		$typeId = $this->createTestType('refint-usage-type');
		$this->createTestNode([
			'uid' => 'refint-usage-node',
			'type' => $typeId,
			'published' => true,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['de' => 'Sudhaus']],
			],
		]);
		$this->insertMenuItem('refint-menu-item', 'refint-used', ['de' => 'Hauptmenü']);

		$sync = new Sync($this->db());
		$sync->replace('node', 'refint-usage-node', ['assets' => ['refint-used'], 'nodes' => []]);
		$sync->replace('menu', 'refint-menu-item', ['assets' => ['refint-used'], 'nodes' => []]);

		$usage = new Usage($this->db())->forAsset('refint-used');

		$this->assertCount(2, $usage);
		[$menu, $node] = $usage;
		$this->assertSame('menu', $menu['ownerType']);
		$this->assertSame('refint-menu-item', $menu['ownerUid']);
		$this->assertSame('Hauptmenü', $menu['title']);
		$this->assertNull($menu['nodeType']);
		$this->assertSame('node', $node['ownerType']);
		$this->assertSame('refint-usage-node', $node['ownerUid']);
		$this->assertSame('Sudhaus', $node['title']);
		$this->assertSame('refint-usage-type', $node['nodeType']);
		$this->assertTrue($node['published']);
	}

	public function testUsageForNodeListsBacklinks(): void
	{
		$typeId = $this->createTestType('refint-backlink-type');
		$this->createTestNode(['uid' => 'refint-backlink-target', 'type' => $typeId]);
		$this->createTestNode([
			'uid' => 'refint-backlink-source',
			'type' => $typeId,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['de' => 'Quelle']],
			],
		]);

		new Sync($this->db())->replace('node', 'refint-backlink-source', [
			'assets' => [],
			'nodes' => ['refint-backlink-target'],
		]);

		$usage = new Usage($this->db())->forNode('refint-backlink-target');

		$this->assertCount(1, $usage);
		$this->assertSame('refint-backlink-source', $usage[0]['ownerUid']);
		$this->assertSame('Quelle', $usage[0]['title']);
	}

	public function testRebuildScansNodesAndMenus(): void
	{
		$this->insertAsset('refint-rb-a');
		$this->insertAsset('refint-rb-b');
		$typeId = $this->createTestType('refint-rb-type');
		$this->createTestNode(['uid' => 'refint-rb-target', 'type' => $typeId]);

		// Bypass createTestNode's content normalization: it would rewrite
		// media items into the legacy {file} shape.
		$this->createTestNode([
			'uid' => 'refint-rb-node',
			'type' => $typeId,
			'content' => json_encode([
				'hero' => [
					'type' => \Cosray\Field\Image::class,
					'value' => ['zxx' => [['uid' => 'refint-rb-a'], ['uid' => 'refint-rb-gone']]],
				],
				'body' => [
					'type' => \Cosray\Field\RichText::class,
					'format' => 'cosray-richtext',
					'version' => 1,
					'value' => [
						'de' => [
							'type' => 'doc',
							'content' => [[
								'type' => 'paragraph',
								'content' => [[
									'type' => 'text',
									'text' => 'Link',
									'marks' => [['type' => 'link', 'attrs' => ['node' => 'refint-rb-target']]],
								]],
							]],
						],
					],
				],
			]),
		]);
		$this->createTestNode([
			'uid' => 'refint-rb-deleted',
			'type' => $typeId,
			'content' => json_encode([
				'hero' => [
					'type' => \Cosray\Field\Image::class,
					'value' => ['zxx' => [['uid' => 'refint-rb-b']]],
				],
			]),
		]);
		$this->db()->execute(
			'UPDATE cms.nodes SET deleted = now() WHERE uid = :uid',
			['uid' => 'refint-rb-deleted'],
		)->run();
		$this->insertMenuItem('refint-rb-menu', 'refint-rb-b', ['de' => 'Menü']);

		$result = new Rebuild($this->db())->run();

		$this->assertSame(['refint-rb-a'], $this->assetRefs('refint-rb-node'));
		$this->assertSame(['refint-rb-target'], $this->nodeRefs('refint-rb-node'));
		// Soft-deleted nodes are skipped, their asset is only held by the menu.
		$this->assertSame([], $this->assetRefs('refint-rb-deleted'));
		$this->assertSame(
			['refint-rb-b'],
			$this->assetRefs('refint-rb-menu', 'menu'),
		);
		$this->assertGreaterThanOrEqual(2, $result['assets']);
		$this->assertGreaterThanOrEqual(1, $result['nodes']);
		$this->assertGreaterThanOrEqual(1, $result['skipped']);
	}

	/** @return list<string> */
	private function assetRefs(string $ownerUid, string $ownerType = 'node'): array
	{
		return array_column(
			$this->db()->execute(
				'SELECT asset_uid FROM cms.asset_references
				WHERE owner_type = :type AND owner_uid = :uid ORDER BY asset_uid',
				['type' => $ownerType, 'uid' => $ownerUid],
			)->all(),
			'asset_uid',
		);
	}

	/** @return list<string> */
	private function nodeRefs(string $ownerUid, string $ownerType = 'node'): array
	{
		return array_column(
			$this->db()->execute(
				'SELECT target_uid FROM cms.node_references
				WHERE owner_type = :type AND owner_uid = :uid ORDER BY target_uid',
				['type' => $ownerType, 'uid' => $ownerUid],
			)->all(),
			'target_uid',
		);
	}

	private function insertAsset(string $uid): void
	{
		$this->db()->execute(
			"INSERT INTO cms.assets (uid, disk, key, filename, kind, creator)
			VALUES (:uid, 'local', :key, 'test.png', 'image', 1)",
			['uid' => $uid, 'key' => substr($uid, 0, 2) . "/{$uid}/test.png"],
		)->run();
	}

	/** @param array<string, string> $title */
	private function insertMenuItem(string $item, string $assetUid, array $title): void
	{
		$this->db()->execute(
			"INSERT INTO cms.menus (menu, description) VALUES ('refint-menu', 'Test menu')
			ON CONFLICT DO NOTHING",
		)->run();
		$this->db()->execute(
			"INSERT INTO cms.menu_items (item, menu, position, data)
			VALUES (:item, 'refint-menu', 1, :data::jsonb)",
			['item' => $item, 'data' => json_encode(['image' => $assetUid, 'title' => $title])],
		)->run();
	}
}
