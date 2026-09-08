<?php

declare(strict_types=1);

namespace Quma\Migrations\M000000_000032_BlockLayoutKeys;

use Celema\Quma\Contract;
use Celema\Quma\Environment;

/**
 * Renames the layout keys of typed block rows in nodes, drafts and both
 * history tables: `layout.span` becomes `colspan` and `layout.rows`
 * becomes `rowspan`, the names the legacy rows already used. Rows that
 * carry the new keys pass through untouched, so the migration is
 * idempotent and a no-op on a database whose typed rows were written
 * after the rename.
 */
final class Migration implements Contract\Migration
{
	/** @var list<array{table: string, keys: list<string>}> */
	private const array CONTENT_TABLES = [
		['table' => '/*:cms.prefix:*/nodes', 'keys' => ['node']],
		['table' => '/*:cms.prefix:*/drafts', 'keys' => ['node']],
		['table' => '/*:cms.prefix:*/nodes_history', 'keys' => ['node', 'changed']],
		['table' => '/*:cms.prefix:*/drafts_history', 'keys' => ['node', 'changed']],
	];

	private const array KEYS = ['span' => 'colspan', 'rows' => 'rowspan'];

	private int $updated = 0;

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

		echo "Renamed the block layout keys of {$this->updated} content rows\n";
	}

	/** @param array{table: string, keys: list<string>} $table */
	private function transformTable(Environment $env, array $table): void
	{
		$keys = implode(', ', $table['keys']);
		$where = implode(' AND ', array_map(static fn(string $key): string => "{$key} = :{$key}", $table['keys']));
		$rows = $env->db->execute($this->sql($env, "
			SELECT {$keys}, content::text AS content
			FROM {$table['table']}
			WHERE content::text LIKE '%\"layout\":%'
		"))->all();

		foreach ($rows as $row) {
			$content = json_decode((string) $row['content'], true);

			if (!is_array($content)) {
				continue;
			}

			$renamed = self::rename($content);

			if ($renamed === $content) {
				continue;
			}

			$params = [];

			foreach ($table['keys'] as $key) {
				$params[$key] = is_int($row[$key]) ? $row[$key] : (string) $row[$key];
			}

			$env->db->execute(
				$this->sql($env, "
				UPDATE {$table['table']}
				SET content = :content::jsonb
				WHERE {$where}
			"),
				$params + ['content' => json_encode($renamed, JSON_THROW_ON_ERROR)],
			)->run();
			$this->updated++;
		}
	}

	/**
	 * A `layout` map holding `span` or `rows` is a typed row's layout; a
	 * field named `layout` holds an envelope and has neither key.
	 */
	private static function rename(array $data): array
	{
		foreach ($data as $key => $value) {
			if (!is_array($value)) {
				continue;
			}

			$data[$key] = $key === 'layout' && array_intersect_key($value, self::KEYS) !== []
				? self::layout($value)
				: self::rename($value);
		}

		return $data;
	}

	private static function layout(array $layout): array
	{
		$renamed = [];

		foreach ($layout as $key => $value) {
			$renamed[self::KEYS[$key] ?? $key] = $value;
		}

		return $renamed;
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
