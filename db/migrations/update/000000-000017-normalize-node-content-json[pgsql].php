<?php

declare(strict_types=1);

namespace Quma\Migrations\M000000_000017_NormalizeNodeContentJson;

use Celemas\Quma\Contract;
use Celemas\Quma\Environment;
use Cosray\Config;
use Cosray\Migration\NodeContentNormalizer;
use Cosray\Uid;

final class Migration implements Contract\Migration
{
	/** @var list<array{table: string, key: string, where: string}> */
	private const array CONTENT_TABLES = [
		[
			'table' => '/*:cms.prefix:*/nodes',
			'key' => 'node',
			'where' => 'node = :node',
		],
		[
			'table' => '/*:cms.prefix:*/drafts',
			'key' => 'node',
			'where' => 'node = :node',
		],
	];

	public function __construct(
		private readonly Config $config,
	) {}

	public function run(Environment $env): void
	{
		$normalizer = new NodeContentNormalizer($this->uidGenerator());
		$this->disableContentTriggers($env);

		try {
			foreach (self::CONTENT_TABLES as $table) {
				$this->normalizeTable($env, $table, $normalizer);
			}
		} finally {
			$this->enableContentTriggers($env);
		}
	}

	/** @param array{table: string, key: string, where: string} $table */
	private function normalizeTable(Environment $env, array $table, NodeContentNormalizer $normalizer): void
	{
		$rows = $env->db->execute($this->sql($env, "
			SELECT {$table['key']}, content::text AS content
			FROM {$table['table']}
		"))->all();

		foreach ($rows as $row) {
			$content = json_decode((string) $row['content'], true);

			if (!is_array($content)) {
				continue;
			}

			$normalized = $normalizer->normalize($content);
			$encoded = json_encode($normalized, JSON_THROW_ON_ERROR);

			if ($encoded === (string) $row['content']) {
				continue;
			}

			$env->db->execute($this->sql($env, "
				UPDATE {$table['table']}
				SET content = :content::jsonb
				WHERE {$table['where']}
			"), [
				'node' => (int) $row['node'],
				'content' => $encoded,
			])->run();
		}
	}

	private function uidGenerator(): Uid
	{
		return new Uid($this->config->uid->alphabet, $this->config->uid->length);
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
