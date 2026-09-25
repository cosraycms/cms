<?php

declare(strict_types=1);

namespace Quma\Migrations\M000000_000041_TextBlocksToRichtext;

use Celema\Quma\Contract;
use Celema\Quma\Environment;
use Cosray\Block\Text;
use Cosray\Migration\TextBlocks;

/**
 * Turns the plain text blocks of nodes, working copies and both history
 * tables into rich text blocks; Cosray\Migration\TextBlocks holds the
 * conversion. The rewrite keeps every row's change time and records no
 * history of its own. Rows without plain text blocks are not touched,
 * so the migration can run again.
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

		echo "Turned the plain text blocks of {$this->updated} content rows into rich text\n";
	}

	/** @param array{table: string, keys: list<string>} $table */
	private function transformTable(Environment $env, array $table): void
	{
		$keys = implode(', ', $table['keys']);
		$where = implode(' AND ', array_map(static fn(string $key): string => "{$key} = :{$key}", $table['keys']));
		// JSON escapes the class name's backslashes the way a jsonpath
		// string wants them.
		$path = 'strict $.** ? (@.type == ' . json_encode(Text::class, JSON_THROW_ON_ERROR) . ')';
		$rows = $env->db->execute(
			$this->sql($env, "
			SELECT {$keys}, content::text AS content
			FROM {$table['table']}
			WHERE jsonb_path_exists(content, :path::jsonpath)
		"),
			['path' => $path],
		)->all();

		foreach ($rows as $row) {
			$content = json_decode((string) $row['content'], true);

			if (!is_array($content)) {
				continue;
			}

			$converted = TextBlocks::content($content);

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
			ALTER TABLE /*:cms.prefix:*/drafts DISABLE TRIGGER /*:cms.obj:*/drafts_trigger_02_change;
			ALTER TABLE /*:cms.prefix:*/drafts DISABLE TRIGGER /*:cms.obj:*/drafts_trigger_01_history;
			SQL))->run();
	}

	private function enableContentTriggers(Environment $env): void
	{
		$env->db->execute($this->sql($env, <<<'SQL'
			ALTER TABLE /*:cms.prefix:*/drafts ENABLE TRIGGER /*:cms.obj:*/drafts_trigger_01_history;
			ALTER TABLE /*:cms.prefix:*/drafts ENABLE TRIGGER /*:cms.obj:*/drafts_trigger_02_change;
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
