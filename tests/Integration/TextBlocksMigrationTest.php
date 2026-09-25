<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Environment;
use Cosray\Block as Builtin;
use Cosray\Field\Blocks;
use Cosray\Field\Textarea;
use Cosray\Migration\TextBlocks;
use Cosray\Tests\IntegrationTestCase;

/**
 * Migration 000000-000041 over plain text blocks in every content table.
 *
 * @internal
 *
 * @coversNothing
 */
final class TextBlocksMigrationTest extends IntegrationTestCase
{
	private const string EARLIER = '2020-01-02 03:04:05+00';

	protected bool $useTransactions = false;

	private int $nodeId;
	private int $typeId;

	protected function setUp(): void
	{
		parent::setUp();

		$this->typeId = $this->createTestType('text-blocks-' . bin2hex(random_bytes(4)));
		$this->nodeId = $this->createTestNode([
			'uid' => 'txtmig' . bin2hex(random_bytes(3)),
			'type' => $this->typeId,
			'content' => json_encode(self::blocks('t1', "Live\ntext"), JSON_THROW_ON_ERROR),
		]);
		$db = $this->db();
		$db->execute(
			'INSERT INTO cms.drafts (node, changed, editor, content)
				VALUES (:node, :changed, 1, :content::jsonb)',
			[
				'node' => $this->nodeId,
				'changed' => self::EARLIER,
				'content' => json_encode(self::blocks('d1', 'Draft'), JSON_THROW_ON_ERROR),
			],
		)->run();
		$db->execute(
			'INSERT INTO cms.nodes_history
				(node, parent, version, changed, published, hidden, locked, type, editor, deleted, content)
				VALUES (:node, NULL, 1, :changed, true, false, false, :type, 1, NULL, :content::jsonb)',
			[
				'node' => $this->nodeId,
				'changed' => self::EARLIER,
				'type' => $this->typeId,
				'content' => json_encode(self::blocks('h1', 'Earlier'), JSON_THROW_ON_ERROR),
			],
		)->run();
		$db->execute(
			'INSERT INTO cms.drafts_history (node, changed, editor, content)
				VALUES (:node, :changed, 1, :content::jsonb)',
			[
				'node' => $this->nodeId,
				'changed' => self::EARLIER,
				'content' => json_encode(self::blocks('h2', 'Earlier draft'), JSON_THROW_ON_ERROR),
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

	public function testTurnsThePlainTextBlocksOfEveryContentTableIntoRichText(): void
	{
		$changed = $this->changed('nodes');

		$this->migrate();

		foreach (['nodes', 'drafts', 'nodes_history', 'drafts_history'] as $table) {
			$block = $this->content($table)['contentBlocks']['value']['zxx'][0];

			$this->assertSame(Builtin\RichText::class, $block['type'], $table);
			$this->assertSame('cosray-richtext', $block['fields']['text']['format'], $table);
		}

		$this->assertEquals(
			[
				'type' => 'doc',
				'content' => [[
					'type' => 'paragraph',
					'content' => [
						['type' => 'text', 'text' => 'Live'],
						['type' => 'hardBreak'],
						['type' => 'text', 'text' => 'text'],
					],
				]],
			],
			$this->content('nodes')['contentBlocks']['value']['zxx'][0]['fields']['text']['value']['zxx'],
		);
		$this->assertSame($changed, $this->changed('nodes'));
		$this->assertSame($this->changedAt(self::EARLIER), $this->changed('drafts'));
		$this->assertSame(1, $this->rowCount('nodes_history'), 'the rewrite must not record history');
		$this->assertSame(1, $this->rowCount('drafts_history'), 'the rewrite must not record history');
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
		$class = 'Quma\Migrations\M000000_000041_TextBlocksToRichtext\Migration';

		if (!class_exists($class)) {
			require self::root() . '/db/migrations/update/000000-000041-text-blocks-to-richtext[pgsql].php';
		}

		$env = new Environment(['default' => $this->conn()], []);
		ob_start();

		try {
			new $class()->run($env);
		} finally {
			ob_end_clean();
		}
	}

	/** @return array<string, mixed> */
	private static function blocks(string $uid, string $text): array
	{
		return [
			'contentBlocks' => [
				'type' => Blocks::class,
				'value' => [
					'zxx' => [[
						'uid' => $uid,
						'type' => TextBlocks::TYPE,
						'layout' => ['col' => 1, 'row' => 1, 'colspan' => 12, 'rowspan' => 1],
						'fields' => ['text' => ['type' => Textarea::class, 'value' => ['zxx' => $text]]],
					]],
				],
			],
		];
	}

	/** @return array<string, mixed> */
	private function content(string $table): array
	{
		$row = $this->db()->execute("SELECT content FROM cms.{$table} WHERE node = :node", [
			'node' => $this->nodeId,
		])->one();

		return json_decode((string) $row['content'], true);
	}

	private function changed(string $table): string
	{
		return (string) $this->db()->execute("SELECT changed::text AS changed FROM cms.{$table} WHERE node = :node", [
			'node' => $this->nodeId,
		])->one()['changed'];
	}

	private function changedAt(string $timestamp): string
	{
		return (string) $this->db()->execute('SELECT :at::timestamptz::text AS at', ['at' => $timestamp])->one()['at'];
	}

	private function rowCount(string $table): int
	{
		return (int) $this->db()->execute("SELECT count(*) AS count FROM cms.{$table} WHERE node = :node", [
			'node' => $this->nodeId,
		])->one()['count'];
	}
}
