<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Environment;
use Cosray\Config;
use Cosray\Tests\IntegrationTestCase;
use Cosray\Tests\Unit\AssetDimensionsTest;
use Quma\Migrations\M000000_000049_AssetDimensions\Migration;

/**
 * Migration 000000-000049 over a catalog with raw dimensions of rotated JPEGs.
 *
 * @internal
 *
 * @coversNothing
 */
final class AssetDimensionsMigrationTest extends IntegrationTestCase
{
	protected bool $useTransactions = false;

	private string $root = '';

	/** @var list<string> */
	private array $uids = [];

	protected function setUp(): void
	{
		parent::setUp();

		$this->root = sys_get_temp_dir() . '/cosray-dimensions-' . bin2hex(random_bytes(6));
		mkdir("{$this->root}/public/assets", 0o755, true);
	}

	protected function tearDown(): void
	{
		foreach ($this->uids as $uid) {
			$this->db()->execute('DELETE FROM cms.assets WHERE uid = :uid', ['uid' => $uid])->run();
		}

		$this->remove($this->root);

		parent::tearDown();
	}

	public function testCorrectsRotatedPhotosAndLeavesOthersAlone(): void
	{
		$turned = $this->insert('turned.jpg', AssetDimensionsTest::jpeg(30, 20, 6), 30, 20);
		$upright = $this->insert('upright.jpg', AssetDimensionsTest::jpeg(31, 21, 1), 31, 21);
		$correct = $this->insert('correct.jpg', AssetDimensionsTest::jpeg(32, 22, 8), 22, 32);
		$gone = $this->insert('gone.jpg', null, 33, 23);
		$png = $this->insert('picture.png', 'not read', 34, 24, 'image/png');

		$output = $this->migrate();

		$this->assertSame([20, 30], $this->stored($turned));
		$this->assertSame([31, 21], $this->stored($upright));
		$this->assertSame([22, 32], $this->stored($correct));
		$this->assertSame([33, 23], $this->stored($gone));
		$this->assertSame([34, 24], $this->stored($png));
		$this->assertMatchesRegularExpression('/Asset dimensions: 1 of \d+ JPEGs corrected/', $output);
		$this->assertStringContainsString('files missing', $output);
	}

	public function testRunningAgainChangesNothing(): void
	{
		$turned = $this->insert('turned.jpg', AssetDimensionsTest::jpeg(30, 20, 6), 30, 20);

		$this->migrate();
		$output = $this->migrate();

		$this->assertSame([20, 30], $this->stored($turned));
		$this->assertMatchesRegularExpression('/Asset dimensions: 0 of \d+ JPEGs corrected/', $output);
	}

	private function migrate(): string
	{
		if (!class_exists(Migration::class)) {
			require self::root() . '/db/migrations/update/000000-000049-asset-dimensions[pgsql].php';
		}

		$config = new Config(self::root(), [
			'db.dsn' => self::databaseDsn(),
			'path.root' => $this->root,
			'path.public' => "{$this->root}/public",
		]);
		ob_start();

		try {
			new Migration($config)->run(new Environment(['default' => $this->conn()], []));
		} finally {
			$output = (string) ob_get_clean();
		}

		return $output;
	}

	private function insert(
		string $filename,
		?string $contents,
		int $width,
		int $height,
		string $mime = 'image/jpeg',
	): string {
		$uid = 'dim' . bin2hex(random_bytes(5));
		$this->uids[] = $uid;
		$key = substr($uid, 0, 2) . "/{$uid}/{$filename}";

		if ($contents !== null) {
			mkdir(dirname("{$this->root}/public/assets/{$key}"), 0o755, true);
			file_put_contents("{$this->root}/public/assets/{$key}", $contents);
		}

		$system = $this->db()->execute("SELECT usr FROM cms.users WHERE type = 'system' LIMIT 1")->one();
		$this->db()->execute(
			"INSERT INTO cms.assets (uid, disk, key, filename, mime, width, height, hash, meta, creator)
				VALUES (:uid, 'local', :key, :filename, :mime, :width, :height, :hash, '{}', :creator)",
			[
				'uid' => $uid,
				'key' => $key,
				'filename' => $filename,
				'mime' => $mime,
				'width' => $width,
				'height' => $height,
				'hash' => hash('sha256', $uid),
				'creator' => (int) $system['usr'],
			],
		)->run();

		return $uid;
	}

	/** @return array{mixed, mixed} */
	private function stored(string $uid): array
	{
		$row = $this->db()->execute('SELECT width, height FROM cms.assets WHERE uid = :uid', ['uid' => $uid])->one();

		return [$row['width'], $row['height']];
	}

	private function remove(string $dir): void
	{
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			is_dir("{$dir}/{$entry}") ? $this->remove("{$dir}/{$entry}") : unlink("{$dir}/{$entry}");
		}

		rmdir($dir);
	}
}
