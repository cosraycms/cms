<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Tests\IntegrationTestCase;

/**
 * Migration 000000-000048 over catalog entries with a credit line.
 *
 * @internal
 *
 * @coversNothing
 */
final class AssetAttributionMigrationTest extends IntegrationTestCase
{
	public function testMovesTheCreditToTheAttribution(): void
	{
		$credited = $this->insert(['credit' => '© Jane Doe', 'alt' => ['en' => 'Kept']]);
		$other = $this->insert(['alt' => ['en' => 'Untouched']]);

		$sql = (string) file_get_contents(self::root() . '/db/migrations/update/000000-000048-asset-attribution.sql');
		$this->db()->execute(str_replace('/*:cms.prefix:*/', 'cms.', $sql))->run();

		$this->assertEquals(['alt' => ['en' => 'Kept'], 'attribution' => '© Jane Doe'], $this->meta($credited));
		$this->assertEquals(['alt' => ['en' => 'Untouched']], $this->meta($other));
	}

	/** @param array<string, mixed> $meta */
	private function insert(array $meta): string
	{
		$uid = 'attr' . bin2hex(random_bytes(6));
		$system = $this->db()->execute("SELECT usr FROM cms.users WHERE type = 'system' LIMIT 1")->one();
		$this->db()->execute(
			"INSERT INTO cms.assets (uid, disk, key, filename, hash, meta, creator)
				VALUES (:uid, 'local', :key, 'pic.png', :hash, :meta, :creator)",
			[
				'uid' => $uid,
				'key' => "at/{$uid}/pic.png",
				'hash' => hash('sha256', $uid),
				'meta' => json_encode($meta),
				'creator' => (int) $system['usr'],
			],
		)->run();

		return $uid;
	}

	/** @return array<string, mixed> */
	private function meta(string $uid): array
	{
		$row = $this->db()->execute('SELECT meta FROM cms.assets WHERE uid = :uid', ['uid' => $uid])->one();

		return json_decode((string) $row['meta'], true);
	}
}
