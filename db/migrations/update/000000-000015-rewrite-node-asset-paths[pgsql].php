<?php

declare(strict_types=1);

namespace Quma\Migrations\M000000_000015_RewriteNodeAssetPaths;

use Celemas\Quma\Contract;
use Celemas\Quma\Environment;

final class Migration implements Contract\Migration
{
	/** @var list<array{table: string, columns: string, where: string}> */
	private const array CONTENT_TABLES = [
		[
			'table' => '/*:cms.prefix:*/nodes',
			'columns' => 'node',
			'where' => 'node = :node',
		],
		[
			'table' => '/*:cms.prefix:*/drafts',
			'columns' => 'node',
			'where' => 'node = :node',
		],
		[
			'table' => '/*:cms.prefix:*/nodes_history',
			'columns' => 'node, changed',
			'where' => 'node = :node AND changed = :changed',
		],
		[
			'table' => '/*:cms.prefix:*/drafts_history',
			'columns' => 'node, changed',
			'where' => 'node = :node AND changed = :changed',
		],
	];

	public function run(Environment $env): void
	{
		$replacements = $this->replacements($env);

		if ($replacements === []) {
			return;
		}

		$this->disableContentTriggers($env);

		try {
			foreach (self::CONTENT_TABLES as $table) {
				$this->rewriteTable($env, $table, $replacements);
			}
		} finally {
			$this->enableContentTriggers($env);
		}
	}

	/**
	 * @param array{table: string, columns: string, where: string} $table
	 * @param array<string, string> $replacements
	 */
	private function rewriteTable(Environment $env, array $table, array $replacements): void
	{
		$rows = $env->db->execute($this->sql($env, "
			SELECT {$table['columns']}, content::text AS content
			FROM {$table['table']}
			WHERE content::text LIKE '%/assets/node/%'
				OR content::text LIKE '%/media/image/node/%'
				OR content::text LIKE '%/media/file/node/%'
				OR content::text LIKE '%/media/video/node/%'
		"))->all();

		foreach ($rows as $row) {
			$content = (string) $row['content'];
			$rewritten = str_replace(
				array_keys($replacements),
				array_values($replacements),
				$content,
			);

			if ($rewritten === $content) {
				continue;
			}

			$params = [
				'node' => (int) $row['node'],
				'content' => $rewritten,
			];

			if (array_key_exists('changed', $row)) {
				$params['changed'] = $row['changed'];
			}

			$env->db->execute($this->sql($env, "
				UPDATE {$table['table']}
				SET content = :content::jsonb
				WHERE {$table['where']}
			"), $params)->run();
		}
	}

	/** @return array<string, string> */
	private function replacements(Environment $env): array
	{
		$rows = $env->db->execute($this->sql($env, '
			SELECT h.handle, n.uid
			FROM /*:cms.prefix:*/node_handles h
			INNER JOIN /*:cms.prefix:*/nodes n ON n.node = h.node
			ORDER BY length(h.handle) DESC
		'))->all();
		$replacements = [];

		foreach ($rows as $row) {
			$handle = (string) $row['handle'];
			$uid = (string) $row['uid'];
			$replacements["/assets/node/{$handle}/"] = "/assets/node/{$uid}/";
			$replacements["/media/image/node/{$handle}/"] = "/media/image/node/{$uid}/";
			$replacements["/media/file/node/{$handle}/"] = "/media/file/node/{$uid}/";
			$replacements["/media/video/node/{$handle}/"] = "/media/video/node/{$uid}/";
		}

		return $replacements;
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
		return $env->conn->applyPlaceholders($sql, __FILE__);
	}
}

return Migration::class;
