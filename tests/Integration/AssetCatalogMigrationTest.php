<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Environment;
use Cosray\Config;
use Cosray\Tests\IntegrationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class AssetCatalogMigrationTest extends IntegrationTestCase
{
	private const string PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

	protected bool $useTransactions = false;

	private string $root;
	private string $nodeUid;
	private int $nodeId;
	private int $typeId;

	protected function setUp(): void
	{
		parent::setUp();

		$this->root = sys_get_temp_dir() . '/cosray-migration-' . bin2hex(random_bytes(4));
		$this->nodeUid = 'mig' . bin2hex(random_bytes(5));
		mkdir("{$this->root}/public/assets/node/{$this->nodeUid}", 0o755, true);
		mkdir("{$this->root}/public/assets/menu/migtest", 0o755, true);
		mkdir("{$this->root}/public/cache/node/{$this->nodeUid}", 0o755, true);

		$png = base64_decode(self::PNG_BASE64, true);
		file_put_contents("{$this->root}/public/assets/node/{$this->nodeUid}/pic.png", $png);
		// NTFS alternate-data-stream artifact: SMB transfers map the
		// illegal `:` to the private-use character U+F03A.
		file_put_contents(
			"{$this->root}/public/assets/node/{$this->nodeUid}/pic.png\u{F03A}Zone.Identifier",
			'ntfs junk',
		);
		file_put_contents("{$this->root}/public/assets/menu/migtest/logo.png", $png);
		file_put_contents(
			"{$this->root}/public/cache/node/{$this->nodeUid}/pic-w400.png",
			$png,
		);

		$this->typeId = $this->createTestType('mig-doc-' . $this->nodeUid);
		$this->nodeId = $this->createTestNode([
			'uid' => $this->nodeUid,
			'type' => $this->typeId,
			'content' => json_encode([
				'gallery' => [
					'type' => 'Cosray\Field\Image',
					'value' => [
						'zxx' => [
							[
								'file' => 'pic.png',
								'meta' => ['alt' => ['en' => ''], 'title' => ['en' => 'Keep']],
							],
						],
					],
				],
				'doc' => [
					'type' => 'Cosray\Field\File',
					'value' => [
						'zxx' => [
							['file' => 'missing.pdf'],
						],
					],
				],
			]),
		]);

		$db = $this->db();
		$db->execute(
			'INSERT INTO cms.drafts (node, changed, editor, content)
				VALUES (:node, now(), 1, :content::jsonb)',
			[
				'node' => $this->nodeId,
				'content' => json_encode([
					'gallery' => [
						'type' => 'Cosray\Field\Image',
						'value' => ['zxx' => [['file' => 'pic.png']]],
					],
				]),
			],
		)->run();
		$db->execute(
			"INSERT INTO cms.nodes_history
				(node, parent, version, changed, published, hidden, locked, type, editor, deleted, content)
				VALUES (:node, null, 1, now() - interval '1 day', true, false, false, :type, 1, null, :content::jsonb)",
			[
				'node' => $this->nodeId,
				'type' => $this->typeId,
				'content' => json_encode([
					'gallery' => ['files' => [['file' => 'pic.png', 'title' => 'Old']]],
				]),
			],
		)->run();
		$db->execute(
			"INSERT INTO cms.drafts_history (node, changed, editor, content)
				VALUES (:node, now() - interval '1 day', 1, :content::jsonb)",
			[
				'node' => $this->nodeId,
				'content' => json_encode([
					'gallery' => ['files' => [['file' => 'pic.png']]],
				]),
			],
		)->run();
		$db->execute(
			"INSERT INTO cms.menus (menu, description) VALUES ('migtest', 'Migration test')
				ON CONFLICT (menu) DO NOTHING",
		)->run();
		$db->execute(
			"INSERT INTO cms.menu_items (item, parent, menu, position, data)
				VALUES (:item, null, 'migtest', 1, :data::jsonb)",
			[
				'item' => "migtest-{$this->nodeUid}",
				'data' => json_encode(['type' => 'page', 'image' => 'logo.png']),
			],
		)->run();
	}

	protected function tearDown(): void
	{
		$db = $this->db();
		$map = $this->map();

		if ($map !== []) {
			$db->execute(
				'DELETE FROM cms.assets WHERE uid IN (SELECT jsonb_array_elements_text(:uids::jsonb))',
				['uids' => json_encode(array_values($map))],
			)->run();
		}

		$db->execute('DELETE FROM cms.menu_items WHERE item = :item', [
			'item' => "migtest-{$this->nodeUid}",
		])->run();
		$db->execute("DELETE FROM cms.menus WHERE menu = 'migtest'")->run();
		$db->execute('DELETE FROM cms.drafts_history WHERE node = :node', [
			'node' => $this->nodeId,
		])->run();
		$db->execute('DELETE FROM cms.drafts WHERE node = :node', ['node' => $this->nodeId])->run();
		$db->execute('DELETE FROM cms.nodes_history WHERE node = :node', [
			'node' => $this->nodeId,
		])->run();
		$db->execute('DELETE FROM cms.nodes WHERE node = :node', ['node' => $this->nodeId])->run();
		$db->execute('DELETE FROM cms.types WHERE type = :type', ['type' => $this->typeId])->run();
		$this->removeDir($this->root);

		parent::tearDown();
	}

	public function testMigratesCatalogContentHistoryMenusAndFiles(): void
	{
		$this->migrate();
		$map = $this->map();

		$picUid = $map["node/{$this->nodeUid}/pic.png"] ?? null;
		$missingUid = $map["node/{$this->nodeUid}/missing.pdf"] ?? null;
		$logoUid = $map['menu/migtest/logo.png'] ?? null;
		$this->assertNotNull($picUid);
		$this->assertNotNull($missingUid);
		$this->assertNotNull($logoUid);

		$png = base64_decode(self::PNG_BASE64, true);
		$pic = $this->assetRow($picUid);
		$this->assertSame('pic.png', $pic['filename']);
		$this->assertSame('image', $pic['kind']);
		$this->assertSame('image/png', $pic['mime']);
		$this->assertSame(hash('sha256', $png), $pic['hash']);
		$this->assertSame(1, (int) $pic['width']);
		$this->assertSame(substr($picUid, 0, 2) . "/{$picUid}/pic.png", $pic['key']);

		$missing = $this->assetRow($missingUid);
		$this->assertSame('file', $missing['kind']);
		$this->assertNull($missing['hash']);
		$this->assertNull($missing['mime']);

		$content = $this->nodeContent();
		$this->assertSame(
			// Empty alt is pruned; the filled title survives.
			['uid' => $picUid, 'meta' => ['title' => ['en' => 'Keep']]],
			$content['gallery']['value']['zxx'][0],
		);
		$this->assertSame(['uid' => $missingUid], $content['doc']['value']['zxx'][0]);

		$draft = $this->decode('SELECT content FROM cms.drafts WHERE node = :node');
		$this->assertSame(['uid' => $picUid], $draft['gallery']['value']['zxx'][0]);

		// History keeps its historical shape; only file → uid is swapped.
		$history = $this->decode('SELECT content FROM cms.nodes_history WHERE node = :node');
		$this->assertSame(
			['uid' => $picUid, 'title' => 'Old'],
			$history['gallery']['files'][0],
		);
		$draftHistory = $this->decode('SELECT content FROM cms.drafts_history WHERE node = :node');
		$this->assertSame(['uid' => $picUid], $draftHistory['gallery']['files'][0]);

		$menu = $this->db()->execute(
			'SELECT data FROM cms.menu_items WHERE item = :item',
			['item' => "migtest-{$this->nodeUid}"],
		)->one();
		$this->assertSame($logoUid, json_decode((string) $menu['data'], true)['image']);

		$sharded = "{$this->root}/public/assets/" . substr($picUid, 0, 2) . "/{$picUid}/pic.png";
		$this->assertFileExists($sharded);
		$this->assertSame($png, file_get_contents($sharded));

		// The unservable NTFS artifact gets no uid and stays in place,
		// so its owner directory survives the cleanup.
		$this->assertCount(3, $map);
		$junk = "pic.png\u{F03A}Zone.Identifier";
		$this->assertFileExists("{$this->root}/public/assets/node/{$this->nodeUid}/{$junk}");
		$this->assertSame(
			[$junk],
			array_values(array_diff(
				scandir("{$this->root}/public/assets/node/{$this->nodeUid}") ?: [],
				['.', '..'],
			)),
		);
		$this->assertDirectoryDoesNotExist("{$this->root}/public/assets/menu");
		$this->assertDirectoryDoesNotExist("{$this->root}/public/cache/node");
	}

	public function testMigrationIsIdempotent(): void
	{
		$this->migrate();
		$contentAfterFirst = $this->nodeContent();
		$mapAfterFirst = $this->map();

		$this->migrate();

		$this->assertSame($mapAfterFirst, $this->map());
		$this->assertSame($contentAfterFirst, $this->nodeContent());

		$count = $this->db()->execute(
			'SELECT count(*) AS count FROM cms.assets
				WHERE uid IN (SELECT jsonb_array_elements_text(:uids::jsonb))',
			['uids' => json_encode(array_values($mapAfterFirst))],
		)->one();
		$this->assertSame(count($mapAfterFirst), (int) $count['count']);
	}

	private function migrate(): void
	{
		$config = new Config(self::root(), [
			'db.dsn' => self::testDbDsn(),
			'path.root' => $this->root,
			'path.public' => "{$this->root}/public",
		]);
		$class = 'Quma\Migrations\M000000_000019_PopulateAssetCatalog\Migration';

		if (!class_exists($class)) {
			require self::root() . '/db/migrations/update/000000-000019-populate-asset-catalog[pgsql].php';
		}

		$env = new Environment(['default' => $this->conn()], []);
		ob_start();

		try {
			new $class($config)->run($env);
		} finally {
			ob_end_clean();
		}
	}

	/** @return array<string, string> */
	private function map(): array
	{
		$file = "{$this->root}/asset-legacy-map.json";

		if (!is_file($file)) {
			return [];
		}

		$decoded = json_decode((string) file_get_contents($file), true);

		return is_array($decoded) ? $decoded : [];
	}

	private function nodeContent(): array
	{
		return $this->decode('SELECT content FROM cms.nodes WHERE node = :node');
	}

	private function decode(string $sql): array
	{
		$row = $this->db()->execute($sql, ['node' => $this->nodeId])->one();

		return json_decode((string) $row['content'], true);
	}

	private function assetRow(string $uid): array
	{
		return $this->db()->execute(
			'SELECT * FROM cms.assets WHERE uid = :uid',
			['uid' => $uid],
		)->one();
	}

	private function removeDir(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}

		foreach (scandir($dir) ?: [] as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$path = "{$dir}/{$item}";
			is_dir($path) && !is_link($path) ? $this->removeDir($path) : unlink($path);
		}

		rmdir($dir);
	}
}
