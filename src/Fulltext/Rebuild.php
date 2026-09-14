<?php

declare(strict_types=1);

namespace Cosray\Fulltext;

use Celema\Container\Container;
use Celema\Quma\Database;
use Cosray\Bootstrap;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Services;
use Cosray\Locales;
use Throwable;

final class Rebuild
{
	private readonly Sync $sync;

	public function __construct(
		private readonly Database $db,
		private readonly Locales $locales,
		Services $services,
		private readonly Container $container,
	) {
		$this->sync = new Sync($db, new Builder($services->types, $services->blocks));
	}

	/**
	 * @param null|callable(string, Throwable): void $failure
	 * @return array{processed: int, indexed: int, empty: int, failed: int, missingTitles: int}
	 */
	public function run(?callable $failure = null): array
	{
		$this->sync->validate($this->locales);
		$classes = [];
		$tag = $this->container->tag(Bootstrap::NODE_TAG);
		foreach ($tag->entries() as $handle) {
			$classes[$handle] = $tag->entry($handle)->definition();
		}

		$report = ['processed' => 0, 'indexed' => 0, 'empty' => 0, 'failed' => 0, 'missingTitles' => 0];
		$ceiling = (int) $this->db->fulltext->ceiling()->one()['node'];
		$after = 0;
		while ($keys = $this->db->fulltext->keys(['after' => $after, 'ceiling' => $ceiling])->all()) {
			foreach ($keys as $key) {
				$after = (int) $key['node'];
				$report['processed']++;
				$ownsTransaction = !$this->db->getConn()->inTransaction();
				if ($ownsTransaction) {
					$this->db->begin();
				} else {
					$this->db->fulltext->savepoint()->run();
				}

				try {
					$result = $this->node($after, $classes);
					if ($ownsTransaction) {
						$this->db->commit();
					} else {
						$this->db->fulltext->releaseSavepoint()->run();
					}
					$report[$result['indexed'] > 0 ? 'indexed' : 'empty']++;
					$report['missingTitles'] += $result['missingTitles'];
				} catch (Throwable $e) {
					if ($ownsTransaction) {
						$this->db->rollback();
					} else {
						$this->db->fulltext->rollbackSavepoint()->run();
						$this->db->fulltext->releaseSavepoint()->run();
					}
					$report['failed']++;
					if ($failure !== null) {
						$failure($key['uid'], $e);
					}
				}
			}
		}
		return $report;
	}

	private function node(int $node, array $classes): array
	{
		// Read after acquiring the same row lock taken by a live UPDATE.
		$row = $this->db->fulltext->lock(['node' => $node])->first();
		if ($row === null || $row['deleted'] !== null) {
			$this->db->fulltext->delete(['node' => $node])->run();
			return ['indexed' => 0, 'missingTitles' => 0];
		}

		$class = $classes[$row['handle']] ?? null;
		if (!is_string($class) || !class_exists($class)) {
			throw new RuntimeException("No configured schema for type '{$row['handle']}'.");
		}

		return $this->sync->replace(
			$node,
			$row['uid'],
			$class,
			json_decode($row['content'], true, flags: JSON_THROW_ON_ERROR),
			json_decode($row['title'], true, flags: JSON_THROW_ON_ERROR),
			$this->locales,
		);
	}
}
