<?php

declare(strict_types=1);

namespace Quma\Migrations\M000000_000013_LegacyUidsToHandles;

use Celema\Quma\Contract;
use Celema\Quma\Environment;
use Cosray\Config;
use Cosray\Uid;
use RuntimeException;

final class Migration implements Contract\Migration
{
	private const string HANDLE_PATTERN = '/^(?!.*[.][.])[A-Za-z0-9](?:[A-Za-z0-9._-]{0,62}[A-Za-z0-9])?$/';
	private const int MAX_UID_ATTEMPTS = 1000;

	public function __construct(
		private readonly Config $config,
	) {}

	public function run(Environment $env): void
	{
		$nodes = $env->db->execute($this->sql(
			$env,
			'SELECT node, uid, creator, editor FROM /*:cms.prefix:*/nodes ORDER BY node FOR UPDATE',
		))->all();
		$candidates = array_values(array_filter(
			$nodes,
			fn(array $node): bool => !$this->isGeneratedUid((string) $node['uid']),
		));

		if ($candidates === []) {
			return;
		}

		$this->assertNoHandleCollisions($env, $nodes, $candidates);

		$uid = $this->uidGenerator();
		$usedUids = array_fill_keys(
			array_map(static fn(array $node): string => (string) $node['uid'], $nodes),
			true,
		);
		$mappings = [];

		foreach ($candidates as $node) {
			$oldUid = (string) $node['uid'];
			$newUid = $this->generateUniqueUid($uid, $usedUids);
			$usedUids[$newUid] = true;
			$mappings[] = [
				'node' => (int) $node['node'],
				'handle' => $oldUid,
				'uid' => $newUid,
				'creator' => (int) $node['creator'],
				'editor' => (int) $node['editor'],
			];
		}

		$this->disableNodeTriggers($env);

		try {
			foreach ($mappings as $mapping) {
				$env->db->execute($this->sql(
					$env,
					'INSERT INTO /*:cms.prefix:*/node_handles (node, handle, creator, editor)
					VALUES (:node, :handle, :creator, :editor)',
				), [
					'node' => $mapping['node'],
					'handle' => $mapping['handle'],
					'creator' => $mapping['creator'],
					'editor' => $mapping['editor'],
				])->run();

				$env->db->execute($this->sql(
					$env,
					'UPDATE /*:cms.prefix:*/nodes SET uid = :uid WHERE node = :node',
				), [
					'node' => $mapping['node'],
					'uid' => $mapping['uid'],
				])->run();
			}
		} finally {
			$this->enableNodeTriggers($env);
		}
	}

	/** @param array<string, true> $usedUids */
	private function generateUniqueUid(Uid $uid, array $usedUids): string
	{
		for ($attempt = 0; $attempt < self::MAX_UID_ATTEMPTS; $attempt++) {
			$newUid = $uid->generate();

			if (!isset($usedUids[$newUid])) {
				return $newUid;
			}
		}

		throw new RuntimeException('Could not generate a unique node uid');
	}

	/**
	 * @param list<array<string, mixed>> $nodes
	 * @param list<array<string, mixed>> $candidates
	 */
	private function assertNoHandleCollisions(Environment $env, array $nodes, array $candidates): void
	{
		$nodeByUid = [];

		foreach ($nodes as $node) {
			$nodeByUid[(string) $node['uid']] = (int) $node['node'];
		}

		$handles = $env->db->execute($this->sql(
			$env,
			'SELECT node, handle FROM /*:cms.prefix:*/node_handles',
		))->all();
		$candidateByHandle = [];

		foreach ($candidates as $node) {
			$handle = (string) $node['uid'];
			$nodeId = (int) $node['node'];

			if (preg_match(self::HANDLE_PATTERN, $handle) !== 1) {
				throw new RuntimeException("Legacy uid '{$handle}' cannot become a handle");
			}

			$candidateByHandle[$handle] = $nodeId;
		}

		foreach ($handles as $handleRow) {
			$handle = (string) $handleRow['handle'];
			$nodeId = (int) $handleRow['node'];

			if (isset($candidateByHandle[$handle]) && $candidateByHandle[$handle] !== $nodeId) {
				throw new RuntimeException("Node handle '{$handle}' already exists");
			}

			if (isset($nodeByUid[$handle]) && $nodeByUid[$handle] !== $nodeId) {
				throw new RuntimeException("Node handle '{$handle}' collides with another node uid");
			}
		}
	}

	private function isGeneratedUid(string $uid): bool
	{
		return preg_match($this->generatedUidPattern(), $uid) === 1;
	}

	private function generatedUidPattern(): string
	{
		return (
			'/^[' . preg_quote($this->config->uid->alphabet, '/') . ']{' . $this->config->uid->length . '}$/'
		);
	}

	private function uidGenerator(): Uid
	{
		return new Uid($this->config->uid->alphabet, $this->config->uid->length);
	}

	private function disableNodeTriggers(Environment $env): void
	{
		$env->db->execute($this->sql($env, <<<'SQL'
			ALTER TABLE /*:cms.prefix:*/nodes DISABLE TRIGGER /*:cms.obj:*/nodes_trigger_02_change;
			ALTER TABLE /*:cms.prefix:*/nodes DISABLE TRIGGER /*:cms.obj:*/nodes_trigger_03_history;
			SQL))->run();
	}

	private function enableNodeTriggers(Environment $env): void
	{
		$env->db->execute($this->sql($env, <<<'SQL'
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
