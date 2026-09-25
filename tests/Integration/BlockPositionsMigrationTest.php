<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Environment;
use Cosray\Bootstrap;
use Cosray\Field\Blocks;
use Cosray\Field\Textarea;
use Cosray\Tests\Fixtures\Block\TextBlock;
use Cosray\Tests\Fixtures\Node\TestMediaDocument;
use Cosray\Tests\IntegrationTestCase;

/**
 * Migration 000000-000040 over blocks that still flow.
 *
 * @internal
 *
 * @coversNothing
 */
final class BlockPositionsMigrationTest extends IntegrationTestCase
{
	protected bool $useTransactions = false;

	private string $handle;
	private int $nodeId;
	private int $typeId;
	private int $strangerId;
	private int $strangerTypeId;

	protected function setUp(): void
	{
		parent::setUp();

		$this->handle = 'positions-' . bin2hex(random_bytes(4));
		$this->typeId = $this->createTestType($this->handle);
		$this->nodeId = $this->createTestNode([
			'uid' => 'posmig' . bin2hex(random_bytes(3)),
			'type' => $this->typeId,
			'content' => json_encode([
				'contentBlocks' => [
					'type' => Blocks::class,
					'value' => [
						'en' => [
							self::text('b1', 'First', ['colspan' => 6, 'rowspan' => 1, 'indent' => 0]),
							self::text('b2', 'Indented', ['colspan' => 7, 'rowspan' => 1, 'indent' => 5]),
						],
						'de' => [self::text('b3', 'Erster', ['colspan' => 12, 'rowspan' => 1, 'indent' => 0])],
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
					'contentBlocks' => [
						'type' => Blocks::class,
						'value' => ['en' => [self::text('d1', 'Draft', [
							'colspan' => 4,
							'rowspan' => 1,
							'indent' => 8,
						])]],
					],
				], JSON_THROW_ON_ERROR),
			],
		)->run();

		// A type no node class is registered for: nothing says what its grid is.
		$this->strangerTypeId = $this->createTestType('stranger-' . bin2hex(random_bytes(4)));
		$this->strangerId = $this->createTestNode([
			'uid' => 'posmix' . bin2hex(random_bytes(3)),
			'type' => $this->strangerTypeId,
			'content' => json_encode([
				'contentBlocks' => [
					'type' => Blocks::class,
					'value' => ['zxx' => [self::text('s1', 'Stranger', [
						'colspan' => 6,
						'rowspan' => 1,
						'indent' => 3,
					])]],
				],
			], JSON_THROW_ON_ERROR),
		]);
	}

	protected function tearDown(): void
	{
		$db = $this->db();
		// The draft first: deleting it records its last state in the history.
		$db->execute('DELETE FROM cms.drafts WHERE node = :node', ['node' => $this->nodeId])->run();

		foreach ([$this->nodeId, $this->strangerId] as $node) {
			$db->execute('DELETE FROM cms.drafts_history WHERE node = :node', ['node' => $node])->run();
			$db->execute('DELETE FROM cms.nodes_history WHERE node = :node', ['node' => $node])->run();
			$db->execute('DELETE FROM cms.nodes WHERE node = :node', ['node' => $node])->run();
		}

		foreach ([$this->typeId, $this->strangerTypeId] as $type) {
			$db->execute('DELETE FROM cms.types WHERE type = :type', ['type' => $type])->run();
		}

		parent::tearDown();
	}

	public function testPlacesTheBlocksWhereTheyFlowedAndStoresTheGrid(): void
	{
		$this->migrate();

		$field = $this->content('nodes', $this->nodeId)['contentBlocks'];

		// jsonb orders keys its own way, so compare without order.
		$this->assertSame(12, $field['columns']);
		$this->assertEquals(
			['colspan' => 6, 'rowspan' => 1, 'col' => 1, 'row' => 1],
			$field['value']['en'][0]['layout'],
		);
		$this->assertEquals(
			['colspan' => 7, 'rowspan' => 1, 'col' => 6, 'row' => 2],
			$field['value']['en'][1]['layout'],
		);
		$this->assertEquals(
			['colspan' => 12, 'rowspan' => 1, 'col' => 1, 'row' => 1],
			$field['value']['de'][0]['layout'],
		);
		$this->assertSame('Indented', $field['value']['en'][1]['fields']['text']['value']['zxx']);
		$this->assertEquals(
			['colspan' => 4, 'rowspan' => 1, 'col' => 9, 'row' => 1],
			$this->content('drafts', $this->nodeId)['contentBlocks']['value']['en'][0]['layout'],
		);
		$this->assertSame(0, $this->rowCount('nodes_history'), 'the rewrite must not spawn history rows');
		// Without a node class there is no grid to place it on.
		$this->assertEquals(
			['colspan' => 6, 'rowspan' => 1, 'indent' => 3],
			$this->content('nodes', $this->strangerId)['contentBlocks']['value']['zxx'][0]['layout'],
		);
	}

	public function testMigrationIsIdempotent(): void
	{
		$this->migrate();
		$first = $this->content('nodes', $this->nodeId);

		$this->migrate();

		$this->assertSame($first, $this->content('nodes', $this->nodeId));
	}

	private function migrate(): void
	{
		$class = 'Quma\Migrations\M000000_000040_BlockPositions\Migration';

		if (!class_exists($class)) {
			require self::root() . '/db/migrations/update/000000-000040-block-positions[pgsql].php';
		}

		$container = $this->container();
		$container->tag(Bootstrap::NODE_TAG)->add($this->handle, TestMediaDocument::class);
		$env = new Environment(['default' => $this->conn()], []);
		ob_start();

		try {
			new $class($container)->run($env);
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

	private function content(string $table, int $node): array
	{
		$row = $this->db()->execute("SELECT content FROM cms.{$table} WHERE node = :node", [
			'node' => $node,
		])->one();

		return json_decode((string) $row['content'], true);
	}

	private function rowCount(string $table): int
	{
		return (int) $this->db()->execute("SELECT count(*) AS count FROM cms.{$table} WHERE node = :node", [
			'node' => $this->nodeId,
		])->one()['count'];
	}
}
