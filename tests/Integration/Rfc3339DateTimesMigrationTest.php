<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Environment;
use Cosray\Field\DateTime;
use Cosray\Tests\IntegrationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class Rfc3339DateTimesMigrationTest extends IntegrationTestCase
{
	protected bool $useTransactions = false;

	private int $nodeId;
	private int $typeId;

	protected function setUp(): void
	{
		parent::setUp();

		$this->typeId = $this->createTestType('rfc3339-' . bin2hex(random_bytes(4)));
		$this->nodeId = $this->createTestNode([
			'uid' => 'dtmig' . bin2hex(random_bytes(3)),
			'type' => $this->typeId,
			'content' => $this->content('2026-07-30 17:00:00', 'UTC'),
		]);
		$this->db()->execute(
			'INSERT INTO cms.drafts (node, changed, editor, content)
				VALUES (:node, now(), 1, :content::jsonb)',
			[
				'node' => $this->nodeId,
				'content' => json_encode($this->content('2026-07-30T19:00', 'Europe/Berlin'), JSON_THROW_ON_ERROR),
			],
		)->run();
		$this->db()->execute(
			'INSERT INTO cms.nodes_history (
				node, parent, version, changed, published, hidden, locked, type, editor, deleted, content
			)
			SELECT node, parent, version, now() - interval \'2 seconds\', published, hidden, locked, type, editor,
				deleted, :content::jsonb
			FROM cms.nodes WHERE node = :node',
			[
				'node' => $this->nodeId,
				'content' => json_encode([
					'observedAt' => [
						'type' => 'datetime',
						'value' => '2026-07-30 19:00:00',
						'timezone' => 'Europe/Berlin',
					],
				], JSON_THROW_ON_ERROR),
			],
		)->run();
		$this->db()->execute(
			'INSERT INTO cms.drafts_history (node, changed, editor, content)
				VALUES (:node, now() - interval \'1 second\', 1, :content::jsonb)',
			[
				'node' => $this->nodeId,
				'content' => json_encode(
					$this->content('2026-07-30T19:00:30+02:00', 'Europe/Berlin'),
					JSON_THROW_ON_ERROR,
				),
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

	public function testNormalizesCurrentDraftAndHistoricalContentWithoutCreatingHistory(): void
	{
		$this->migrate();

		$this->assertSame('2026-07-30T17:00:00Z', $this->value('nodes'));
		$this->assertSame('2026-07-30T17:00:00Z', $this->value('drafts'));
		$this->assertSame('2026-07-30T17:00:00Z', $this->value('nodes_history'));
		$this->assertSame('2026-07-30T17:00:30Z', $this->value('drafts_history'));
		$this->assertSame(1, $this->rowCount('nodes_history'));
		$this->assertSame(1, $this->rowCount('drafts_history'));
	}

	public function testMigrationIsIdempotent(): void
	{
		$this->migrate();
		$first = $this->value('nodes');

		$this->migrate();

		$this->assertSame($first, $this->value('nodes'));
	}

	private function migrate(): void
	{
		$class = 'Quma\\Migrations\\M000000_000033_Rfc3339Datetimes\\Migration';

		if (!class_exists($class)) {
			require self::root() . '/db/migrations/update/000000-000033-rfc3339-datetimes[pgsql].php';
		}

		$env = new Environment(['default' => $this->conn()], []);
		ob_start();

		try {
			new $class()->run($env);
		} finally {
			ob_end_clean();
		}
	}

	private function content(string $value, string $timezone): array
	{
		return [
			'observedAt' => [
				'type' => DateTime::class,
				'value' => ['zxx' => $value],
				'meta' => ['timezone' => ['zxx' => $timezone]],
			],
		];
	}

	private function value(string $table): string
	{
		$row = $this->db()->execute("SELECT content FROM cms.{$table} WHERE node = :node", [
			'node' => $this->nodeId,
		])->one();
		$content = json_decode((string) $row['content'], true);
		$value = $content['observedAt']['value'];

		return is_array($value) ? (string) $value['zxx'] : (string) $value;
	}

	private function rowCount(string $table): int
	{
		$row = $this->db()->execute("SELECT count(*) AS count FROM cms.{$table} WHERE node = :node", [
			'node' => $this->nodeId,
		])->one();

		return (int) $row['count'];
	}
}
