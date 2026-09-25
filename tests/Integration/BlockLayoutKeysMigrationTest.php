<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Environment;
use Cosray\Field\Blocks;
use Cosray\Field\Textarea;
use Cosray\Tests\Fixtures\Block\TextBlock;
use Cosray\Tests\IntegrationTestCase;

/**
 * Migration 000000-000032 over typed rows written before the layout
 * keys were renamed.
 *
 * @internal
 *
 * @coversNothing
 */
final class BlockLayoutKeysMigrationTest extends IntegrationTestCase
{
	protected bool $useTransactions = false;

	private int $nodeId;
	private int $typeId;

	protected function setUp(): void
	{
		parent::setUp();

		$this->typeId = $this->createTestType('layout-keys-' . bin2hex(random_bytes(4)));
		$this->nodeId = $this->createTestNode([
			'uid' => 'lkmig' . bin2hex(random_bytes(3)),
			'type' => $this->typeId,
			'content' => json_encode([
				'layout' => ['type' => 'option', 'value' => ['zxx' => 'wide']],
				'blocks' => [
					'type' => Blocks::class,
					'value' => [
						'en' => [
							self::text('b1', 'Old keys', ['span' => 8, 'rows' => 2, 'indent' => 4]),
							self::text('b2', 'New keys', ['colspan' => 6, 'rowspan' => 1, 'indent' => 0]),
						],
					],
				],
			], JSON_THROW_ON_ERROR),
		]);
		$this->db()->execute(
			'INSERT INTO cms.drafts (node, changed, editor, content)
				VALUES (:node, now(), 1, :content::jsonb)',
			[
				'node' => $this->nodeId,
				'content' => json_encode([
					'blocks' => [
						'type' => Blocks::class,
						'value' => ['zxx' => [self::text('d1', 'Draft', ['span' => 12, 'rows' => 1, 'indent' => 0])]],
					],
				], JSON_THROW_ON_ERROR),
			],
		)->run();
	}

	protected function tearDown(): void
	{
		$db = $this->db();
		// The draft first: deleting it records its last state in the history.
		$db->execute('DELETE FROM cms.drafts WHERE node = :node', ['node' => $this->nodeId])->run();
		$db->execute('DELETE FROM cms.drafts_history WHERE node = :node', ['node' => $this->nodeId])->run();
		$db->execute('DELETE FROM cms.nodes_history WHERE node = :node', ['node' => $this->nodeId])->run();
		$db->execute('DELETE FROM cms.nodes WHERE node = :node', ['node' => $this->nodeId])->run();
		$db->execute('DELETE FROM cms.types WHERE type = :type', ['type' => $this->typeId])->run();

		parent::tearDown();
	}

	public function testRenamesTheLayoutKeysAndLeavesEverythingElseAlone(): void
	{
		$this->migrate();

		$content = $this->content('nodes');
		$rows = $content['blocks']['value']['en'];

		// jsonb orders keys its own way, so compare without order.
		$this->assertEquals(['colspan' => 8, 'rowspan' => 2, 'indent' => 4], $rows[0]['layout']);
		$this->assertEquals(['colspan' => 6, 'rowspan' => 1, 'indent' => 0], $rows[1]['layout']);
		$this->assertSame('Old keys', $rows[0]['fields']['text']['value']['zxx']);
		$this->assertSame(['type' => 'option', 'value' => ['zxx' => 'wide']], $content['layout']);
		$this->assertEquals(
			['colspan' => 12, 'rowspan' => 1, 'indent' => 0],
			$this->content('drafts')['blocks']['value']['zxx'][0]['layout'],
		);
		$this->assertSame(0, $this->rowCount('nodes_history'), 'the rewrite must not spawn history rows');
	}

	public function testMigrationIsIdempotent(): void
	{
		$this->migrate();
		$first = $this->content('nodes');

		$this->migrate();

		$this->assertSame($first, $this->content('nodes'));
	}

	private function migrate(): void
	{
		$class = 'Quma\Migrations\M000000_000032_BlockLayoutKeys\Migration';

		if (!class_exists($class)) {
			require self::root() . '/db/migrations/update/000000-000032-block-layout-keys[pgsql].php';
		}

		$env = new Environment(['default' => $this->conn()], []);
		ob_start();

		try {
			new $class()->run($env);
		} finally {
			ob_end_clean();
		}
	}

	private static function text(string $uid, string $text, array $layout): array
	{
		return [
			'uid' => $uid,
			'type' => TextBlock::class,
			'layout' => $layout,
			'fields' => ['text' => ['type' => Textarea::class, 'value' => ['zxx' => $text]]],
		];
	}

	private function content(string $table): array
	{
		$row = $this->db()->execute("SELECT content FROM cms.{$table} WHERE node = :node", [
			'node' => $this->nodeId,
		])->one();

		return json_decode((string) $row['content'], true);
	}

	private function rowCount(string $table): int
	{
		$row = $this->db()->execute("SELECT count(*) AS count FROM cms.{$table} WHERE node = :node", [
			'node' => $this->nodeId,
		])->one();

		return (int) $row['count'];
	}
}
