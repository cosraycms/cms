<?php

declare(strict_types=1);

namespace Quma\Migrations\M000000_000031_BlocksTypedRows;

use Celema\Quma\Contract;
use Celema\Quma\Environment;
use Cosray\Config;
use Cosray\Migration\BlockRowConverter;
use Cosray\Uid;

/**
 * Reshapes every stored blocks field — nodes, drafts and both history
 * tables — from the legacy blocks `{type: id, colspan, rowspan,
 * colstart, width, value, meta}` to the typed rows `{uid, type: class,
 * layout: {span, rows, indent}, fields, meta?}` the rebuilt Blocks field
 * reads and validates; Cosray\Migration\BlockRowConverter holds the
 * conversion table.
 *
 * Layouts are copied, never clamped: the field's columns are unknown
 * here, every reader clamps. Blocks of unknown type stay untouched and
 * are listed, with everything else worth a look, in
 * `blocks-migration-report.json` at the project root.
 */
final class Migration implements Contract\Migration
{
	/** @var list<array{name: string, table: string, keys: list<string>}> */
	private const array CONTENT_TABLES = [
		['name' => 'nodes', 'table' => '/*:cms.prefix:*/nodes', 'keys' => ['node']],
		['name' => 'drafts', 'table' => '/*:cms.prefix:*/drafts', 'keys' => ['node']],
		['name' => 'nodes_history', 'table' => '/*:cms.prefix:*/nodes_history', 'keys' => ['node', 'changed']],
		['name' => 'drafts_history', 'table' => '/*:cms.prefix:*/drafts_history', 'keys' => ['node', 'changed']],
	];

	private readonly BlockRowConverter $converter;
	private int $rows = 0;
	private int $updated = 0;

	public function __construct(
		private readonly Config $config,
	) {
		$this->converter = new BlockRowConverter(new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13));
	}

	public function run(Environment $env): void
	{
		$this->disableContentTriggers($env);

		try {
			foreach (self::CONTENT_TABLES as $table) {
				$this->transformTable($env, $table);
			}
		} finally {
			$this->enableContentTriggers($env);
		}

		$this->writeReport();
	}

	/** @param array{name: string, table: string, keys: list<string>} $table */
	private function transformTable(Environment $env, array $table): void
	{
		$keys = implode(', ', $table['keys']);
		$where = implode(' AND ', array_map(static fn(string $key): string => "{$key} = :{$key}", $table['keys']));
		$rows = $env->db->execute($this->sql($env, "
			SELECT {$keys}, content::text AS content
			FROM {$table['table']}
		"))->all();

		foreach ($rows as $row) {
			$content = json_decode((string) $row['content'], true);

			if (!is_array($content)) {
				continue;
			}

			$this->rows++;
			$params = [];

			foreach ($table['keys'] as $key) {
				$params[$key] = is_int($row[$key]) ? $row[$key] : (string) $row[$key];
			}

			$converted = $this->converter->convert($content, $table['name'], implode('/', $params));

			// jsonb normalizes key order and spacing, so the decoded
			// arrays, not the encoded strings, tell whether anything changed.
			if ($converted === $content) {
				continue;
			}

			$encoded = json_encode($converted, JSON_THROW_ON_ERROR);

			$env->db->execute(
				$this->sql($env, "
				UPDATE {$table['table']}
				SET content = :content::jsonb
				WHERE {$where}
			"),
				$params + ['content' => $encoded],
			)->run();
			$this->updated++;
		}
	}

	private function writeReport(): void
	{
		$report = ['rows' => $this->rows, 'updated' => $this->updated] + $this->converter->report();
		$path = $this->config->get('path.root') . '/blocks-migration-report.json';
		file_put_contents(
			$path,
			json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
		);
		echo "Blocks migration report written to {$path}\n";
	}

	private function disableContentTriggers(Environment $env): void
	{
		$env->db->execute($this->sql($env, <<<'SQL'
			ALTER TABLE /*:cms.prefix:*/nodes DISABLE TRIGGER /*:cms.obj:*/nodes_trigger_02_change;
			ALTER TABLE /*:cms.prefix:*/nodes DISABLE TRIGGER /*:cms.obj:*/nodes_trigger_03_history;
			ALTER TABLE /*:cms.prefix:*/drafts DISABLE TRIGGER /*:cms.obj:*/drafts_trigger_01_history;
			SQL))->run();
	}

	private function enableContentTriggers(Environment $env): void
	{
		$env->db->execute($this->sql($env, <<<'SQL'
			ALTER TABLE /*:cms.prefix:*/drafts ENABLE TRIGGER /*:cms.obj:*/drafts_trigger_01_history;
			ALTER TABLE /*:cms.prefix:*/nodes ENABLE TRIGGER /*:cms.obj:*/nodes_trigger_03_history;
			ALTER TABLE /*:cms.prefix:*/nodes ENABLE TRIGGER /*:cms.obj:*/nodes_trigger_02_change;
			SQL))->run();
	}

	private function sql(Environment $env, string $sql): string
	{
		return $env->conn->config->placeholders?->compileSql($sql, __FILE__) ?? $sql;
	}
}

return Migration::class;
