<?php

declare(strict_types=1);

namespace Quma\Migrations\M000000_000040_BlockPositions;

use Celema\Container\Container;
use Celema\Quma\Contract;
use Celema\Quma\Environment;
use Cosray\Bootstrap;
use Cosray\Migration\BlockPositions;

/**
 * Gives the blocks in nodes, drafts and both history tables the
 * position the flow put them at and stores the grid they were laid out
 * on, read from the node type's `#[Columns]`: from here on a block sits
 * where it is placed, and changing `#[Columns]` only affects new
 * content. Cosray\Migration\BlockPositions holds the conversion. Rows
 * of a type no registered node class has are left as they are and
 * counted; readers place their blocks in order.
 */
final class Migration implements Contract\Migration
{
	/** @var list<array{table: string, keys: list<string>, from: string}> */
	private const array CONTENT_TABLES = [
		[
			'table' => '/*:cms.prefix:*/nodes',
			'keys' => ['node'],
			'from' => '/*:cms.prefix:*/nodes c JOIN /*:cms.prefix:*/types t ON t.type = c.type',
		],
		[
			'table' => '/*:cms.prefix:*/drafts',
			'keys' => ['node'],
			'from' =>
				'/*:cms.prefix:*/drafts c JOIN /*:cms.prefix:*/nodes n ON n.node = c.node'
					. ' JOIN /*:cms.prefix:*/types t ON t.type = n.type',
		],
		[
			'table' => '/*:cms.prefix:*/nodes_history',
			'keys' => ['node', 'changed'],
			'from' => '/*:cms.prefix:*/nodes_history c JOIN /*:cms.prefix:*/types t ON t.type = c.type',
		],
		[
			'table' => '/*:cms.prefix:*/drafts_history',
			'keys' => ['node', 'changed'],
			'from' =>
				'/*:cms.prefix:*/drafts_history c JOIN /*:cms.prefix:*/nodes n ON n.node = c.node'
					. ' JOIN /*:cms.prefix:*/types t ON t.type = n.type',
		],
	];

	/** @var array<string, array<string, array{columns: int, min: int}>> */
	private array $grids = [];

	private int $updated = 0;
	private int $unknown = 0;

	public function __construct(
		private readonly Container $container,
	) {}

	public function run(Environment $env): void
	{
		$tag = $this->container->tag(Bootstrap::NODE_TAG);

		foreach ($tag->entries() as $handle) {
			$class = $tag->entry($handle)->definition();

			if (is_string($class) && class_exists($class)) {
				$this->grids[$handle] = BlockPositions::grids($class);
			}
		}

		$this->disableContentTriggers($env);

		try {
			foreach (self::CONTENT_TABLES as $table) {
				$this->transformTable($env, $table);
			}
		} finally {
			$this->enableContentTriggers($env);
		}

		echo "Placed the blocks of {$this->updated} content rows\n";

		if ($this->unknown > 0) {
			echo "Left {$this->unknown} content rows of unregistered node types as they are\n";
		}
	}

	/** @param array{table: string, keys: list<string>, from: string} $table */
	private function transformTable(Environment $env, array $table): void
	{
		$keys = implode(', ', array_map(static fn(string $key): string => "c.{$key}", $table['keys']));
		$where = implode(' AND ', array_map(static fn(string $key): string => "{$key} = :{$key}", $table['keys']));
		$rows = $env->db->execute($this->sql($env, "
			SELECT {$keys}, t.handle, c.content::text AS content
			FROM {$table['from']}
			WHERE c.content::text LIKE '%\"layout\":%'
		"))->all();

		foreach ($rows as $row) {
			$grids = $this->grids[(string) $row['handle']] ?? null;
			$content = json_decode((string) $row['content'], true);

			if ($grids === null) {
				$this->unknown++;

				continue;
			}

			if (!is_array($content) || $grids === []) {
				continue;
			}

			$placed = BlockPositions::content($content, $grids);

			if ($placed === $content) {
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
				$params + ['content' => json_encode($placed, JSON_THROW_ON_ERROR)],
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
