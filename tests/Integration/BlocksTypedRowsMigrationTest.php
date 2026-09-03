<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Environment;
use Celema\Sire\Issue;
use Cosray\Block\Types;
use Cosray\Config;
use Cosray\Field\Blocks;
use Cosray\Field\Services;
use Cosray\Node\FieldOwner;
use Cosray\Schema\TranslateMode;
use Cosray\Tests\IntegrationTestCase;
use Cosray\Value\ValueContext;

/**
 * Migration 000000-000031 over seeded rows: every migrated blocks field
 * has to pass the rebuilt field's shape — a migrated block that would
 * fail its next save is a migration bug.
 *
 * @internal
 *
 * @coversNothing
 */
final class BlocksTypedRowsMigrationTest extends IntegrationTestCase
{
	private const string ENVELOPE_FORMAT = 'cosray-richtext';

	private const array DOC = [
		'type' => 'doc',
		'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Welcome']]]],
	];

	/** Before migration 017: a bare field id and a plain list. */
	private const array PRE_017_CONTENT = [
		'blocks' => [
			'type' => 'blocks',
			'columns' => 12,
			'value' => [['type' => 'text', 'colspan' => 12, 'rowspan' => 1, 'value' => 'old']],
		],
	];

	protected bool $useTransactions = false;

	private string $root;
	private int $nodeId;
	private int $typeId;

	protected function setUp(): void
	{
		parent::setUp();

		$this->root = sys_get_temp_dir() . '/cosray-blocks-migration-' . bin2hex(random_bytes(4));
		mkdir($this->root, 0o755, true);
		$this->typeId = $this->createTestType('blocks-mig-' . bin2hex(random_bytes(4)));
		$this->nodeId = $this->createTestNode([
			'uid' => 'blkmig' . bin2hex(random_bytes(3)),
			'type' => $this->typeId,
			'content' => json_encode($this->legacyNode(), JSON_THROW_ON_ERROR),
		]);

		$db = $this->db();
		$db->execute(
			'INSERT INTO cms.drafts (node, changed, editor, content)
				VALUES (:node, now(), 1, :content::jsonb)',
			[
				'node' => $this->nodeId,
				'content' => json_encode([
					'blocks' => [
						'type' => 'Cosray\Field\Blocks',
						'value' => [
							'en' => [$this->legacyText(null, 'Draft', 12)],
							'de' => null,
						],
					],
				], JSON_THROW_ON_ERROR),
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
					'blocks' => [
						'type' => 'Cosray\Field\Blocks',
						'value' => ['en' => [$this->legacyHeading('h1', 'Old title')]],
						'meta' => ['columns' => ['zxx' => 12]],
					],
				], JSON_THROW_ON_ERROR),
			],
		)->run();
		$db->execute(
			"INSERT INTO cms.drafts_history (node, changed, editor, content)
				VALUES (:node, now() - interval '1 day', 1, :content::jsonb)",
			['node' => $this->nodeId, 'content' => json_encode(self::PRE_017_CONTENT, JSON_THROW_ON_ERROR)],
		)->run();
	}

	protected function tearDown(): void
	{
		$db = $this->db();
		$db->execute('DELETE FROM cms.drafts_history WHERE node = :node', ['node' => $this->nodeId])->run();
		$db->execute('DELETE FROM cms.drafts WHERE node = :node', ['node' => $this->nodeId])->run();
		$db->execute('DELETE FROM cms.nodes_history WHERE node = :node', ['node' => $this->nodeId])->run();
		$db->execute('DELETE FROM cms.nodes WHERE node = :node', ['node' => $this->nodeId])->run();
		$db->execute('DELETE FROM cms.types WHERE type = :type', ['type' => $this->typeId])->run();

		foreach (glob("{$this->root}/*") ?: [] as $file) {
			unlink($file);
		}

		rmdir($this->root);

		parent::tearDown();
	}

	public function testMigratedFieldsPassTheBlocksShape(): void
	{
		$this->migrate();

		$content = $this->content('nodes');
		$draft = $this->content('drafts');
		$history = $this->content('nodes_history');

		$this->assertValidBlocks($content['blocks'], TranslateMode::Asymmetric);
		$this->assertValidBlocks($content['stacked'], null);
		$this->assertValidBlocks($draft['blocks'], TranslateMode::Asymmetric);
		$this->assertValidBlocks($history['blocks'], TranslateMode::Asymmetric);

		$en = $content['blocks']['value']['en'];
		$this->assertEquals(['en' => 'Blocks', 'de' => 'Blöcke'], $content['title']['value']);
		$this->assertArrayNotHasKey('meta', $content['blocks']);
		$this->assertSame(
			[
				Types\RichText::class,
				Types\Heading::class,
				Types\Text::class,
				Types\Image::class,
				Types\Youtube::class,
				Types\Iframe::class,
			],
			array_column($en, 'type'),
		);
		$this->assertEquals(['span' => 8, 'rows' => 1, 'indent' => 2], $en[0]['layout']);
		$this->assertSame(['class' => ['zxx' => 'lead']], $en[0]['meta']);
		$this->assertSame(['zxx' => '3'], $en[1]['fields']['level']['value']);
		$this->assertEquals(['span' => 4, 'rows' => 2, 'indent' => 0], $en[2]['layout']);
		$this->assertArrayNotHasKey('width', $en[2]);
		$this->assertSame(
			['aspectRatioX' => ['zxx' => 16], 'aspectRatioY' => ['zxx' => 9]],
			$en[4]['fields']['video']['meta'],
		);
		$this->assertArrayNotHasKey('meta', $en[4]);
		$this->assertMatchesRegularExpression('/^[123456789bcdfghklmnpqrstvwxyz]{13}$/', $en[5]['uid']);
		$this->assertSame(Types\Text::class, $content['blocks']['value']['de'][0]['type']);
		$this->assertSame(['zxx' => '2'], $content['stacked']['value']['zxx'][0]['fields']['level']['value']);
		$this->assertCount(2, $content['stacked']['value']['zxx'][1]['fields']['images']['value']['zxx']);
		$this->assertSame(Types\Heading::class, $history['blocks']['value']['en'][0]['type']);
		$this->assertArrayNotHasKey('meta', $history['blocks']);

		// A pre-017 snapshot is not a blocks field the converter knows.
		$this->assertEquals(self::PRE_017_CONTENT, $this->content('drafts_history'));
		// The rewrite bypassed the history trigger.
		$this->assertSame(1, $this->rowCount('nodes_history'));

		$report = json_decode((string) file_get_contents("{$this->root}/blocks-migration-report.json"), true);
		// The report spans the whole database; only the changes are the fixture's alone.
		$this->assertSame(3, $report['updated']);
		$this->assertSame(4, $report['fields']);
		$this->assertSame(11, $report['blocks']);
		$this->assertSame(2, $report['uidsGenerated']);
		$this->assertSame(['class' => 1], $report['metaKeys']);
		$this->assertSame([], $report['unknownTypes']);
	}

	public function testMigrationIsIdempotent(): void
	{
		$this->migrate();
		$first = $this->content('nodes');

		$this->migrate();

		$this->assertSame($first, $this->content('nodes'));
		$report = json_decode((string) file_get_contents("{$this->root}/blocks-migration-report.json"), true);
		$this->assertSame(0, $report['updated']);
		$this->assertSame(0, $report['blocks']);
	}

	private function migrate(): void
	{
		$config = new Config(self::root(), ['db.dsn' => self::testDbDsn(), 'path.root' => $this->root]);
		$class = 'Quma\Migrations\M000000_000031_BlocksTypedRows\Migration';

		if (!class_exists($class)) {
			require self::root() . '/db/migrations/update/000000-000031-blocks-typed-rows[pgsql].php';
		}

		$env = new Environment(['default' => $this->conn()], []);
		ob_start();

		try {
			new $class($config)->run($env);
		} finally {
			ob_end_clean();
		}
	}

	private function assertValidBlocks(array $field, ?TranslateMode $mode): void
	{
		$owner = new FieldOwner($this->createContext(), 'blocks-migration');
		$blocks = new Blocks('blocks', $owner, new ValueContext('blocks', []));
		$blocks->init(Services::withDefaults());
		$blocks->columns(12, 2)->translate($mode);
		$result = $blocks->shape()->validate($field);

		$this->assertTrue($result->valid(), implode("\n", array_map(
			static fn(Issue $issue): string => implode('.', $issue->path) . ': ' . $issue->message,
			$result->issues(),
		)));
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

	private function legacyNode(): array
	{
		return [
			'title' => ['type' => 'Cosray\Field\Text', 'value' => ['en' => 'Blocks', 'de' => 'Blöcke']],
			'blocks' => [
				'type' => 'Cosray\Field\Blocks',
				'value' => [
					'en' => [
						[
							'type' => 'richtext',
							'uid' => 'rich000000001',
							'colspan' => 8,
							'rowspan' => 1,
							'colstart' => 3,
							'format' => self::ENVELOPE_FORMAT,
							'version' => 1,
							'value' => ['zxx' => self::DOC],
							'meta' => ['class' => ['zxx' => 'lead']],
						],
						$this->legacyHeading('h3', 'Opening hours'),
						[
							'type' => 'text',
							'uid' => 'text000000001',
							'colspan' => 4,
							'rowspan' => 2,
							'width' => 33,
							'value' => ['zxx' => "Mon-Fri\n9-17"],
						],
						[
							'type' => 'image',
							'uid' => 'imag000000001',
							'colspan' => 6,
							'rowspan' => 1,
							'colstart' => null,
							'value' => [['uid' => 'asset00000001', 'meta' => ['alt' => ['zxx' => 'Front']]]],
						],
						[
							'type' => 'youtube',
							'uid' => 'yout000000001',
							'colspan' => 6,
							'rowspan' => 1,
							'colstart' => 7,
							'value' => ['zxx' => 'dQw4w9WgXcQ'],
							'meta' => ['aspectRatioX' => ['zxx' => 16], 'aspectRatioY' => ['zxx' => 9]],
						],
						[
							'type' => 'iframe',
							'colspan' => 12,
							'rowspan' => 1,
							'value' => ['zxx' => '<iframe src="https://example.com"></iframe>'],
						],
					],
					'de' => [$this->legacyText('text000000002', 'Mo-Fr 9-17', 12)],
				],
				'meta' => ['columns' => ['zxx' => 12], 'minCellWidth' => ['zxx' => 2]],
			],
			'stacked' => [
				'type' => 'Cosray\Field\Blocks',
				'value' => [
					'zxx' => [
						$this->legacyHeading('h2', 'Gallery'),
						[
							'type' => 'images',
							'uid' => 'imgs000000001',
							'colspan' => 12,
							'rowspan' => 1,
							'value' => [['uid' => 'asset00000002'], ['uid' => 'asset00000003']],
						],
					],
				],
			],
		];
	}

	private function legacyText(?string $uid, string $text, int $colspan): array
	{
		$block = ['type' => 'text', 'colspan' => $colspan, 'rowspan' => 1, 'value' => ['zxx' => $text]];

		return $uid === null ? $block : ['uid' => $uid] + $block;
	}

	private function legacyHeading(string $type, string $text): array
	{
		return [
			'type' => $type,
			'uid' => 'head' . str_pad($type, 9, '0', STR_PAD_LEFT),
			'colspan' => 12,
			'rowspan' => 1,
			'value' => ['zxx' => $text],
		];
	}
}
