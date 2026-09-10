<?php

declare(strict_types=1);

namespace Quma\Migrations\M000000_000033_Rfc3339Datetimes;

use Celema\Quma\Contract;
use Celema\Quma\Environment;
use Cosray\Migration\DateTimeConverter;

final class Migration implements Contract\Migration
{
	/** @var list<array{table: string, keys: list<string>}> */
	private const array CONTENT_TABLES = [
		['table' => '/*:cms.prefix:*/nodes', 'keys' => ['node']],
		['table' => '/*:cms.prefix:*/drafts', 'keys' => ['node']],
		['table' => '/*:cms.prefix:*/nodes_history', 'keys' => ['node', 'changed']],
		['table' => '/*:cms.prefix:*/drafts_history', 'keys' => ['node', 'changed']],
	];

	private readonly DateTimeConverter $converter;
	private int $updated = 0;

	public function __construct()
	{
		$this->converter = new DateTimeConverter();
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

		echo "Normalized {$this->updated} content rows to RFC 3339 date/times\n";
	}

	/** @param array{table: string, keys: list<string>} $table */
	private function transformTable(Environment $env, array $table): void
	{
		$keys = implode(', ', $table['keys']);
		$where = implode(' AND ', array_map(static fn(string $key): string => "{$key} = :{$key}", $table['keys']));
		$rows = $env->db->execute($this->sql($env, "
			SELECT {$keys}, content::text AS content
			FROM {$table['table']}
			WHERE content::text LIKE '%DateTime%' OR content::text LIKE '%\"datetime\"%'
		"))->all();

		foreach ($rows as $row) {
			$content = json_decode((string) $row['content'], true);

			if (!is_array($content)) {
				continue;
			}

			$converted = $this->converter->convert($content, $table['table']);

			if ($converted === $content) {
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
				$params + ['content' => json_encode($converted, JSON_THROW_ON_ERROR)],
			)->run();
			$this->updated++;
		}
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
