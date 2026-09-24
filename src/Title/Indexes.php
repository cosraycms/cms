<?php

declare(strict_types=1);

namespace Cosray\Title;

use Celema\Quma\Database;
use Cosray\Exception\RuntimeException;
use Cosray\Locales;
use Throwable;

/** Reconciles only the title indexes on the configured nodes table. */
final class Indexes
{
	public function __construct(
		private readonly Database $db,
		private readonly Locales $locales,
	) {}

	/** @return array{created: int, dropped: int, locales: list<string>} */
	public function reconcile(): array
	{
		$owned = !$this->db->getConn()->inTransaction();
		if ($owned) {
			$this->db->begin();
		}

		try {
			$result = $this->update();
			if ($owned) {
				$this->db->commit();
			}
			return $result;
		} catch (Throwable $error) {
			if ($owned) {
				$this->db->rollback();
			}
			throw $error;
		}
	}

	private function update(): array
	{
		$sort = new Sort($this->db);
		$wanted = [];
		foreach ($this->locales as $locale) {
			$name = Sort::indexName($locale->id);
			if (strlen($name) > 63 || isset($wanted[$name])) {
				throw new RuntimeException("Title sort index name is too long or ambiguous: '{$name}'");
			}
			$wanted[$name] = ['locale' => $locale->id, 'expression' => $sort->order($locale)];
		}

		$existing = $this->existing();
		$created = 0;
		$dropped = 0;
		foreach ($wanted as $name => $definition) {
			$source = hash('sha256', $definition['expression']);
			$current = $existing[$name] ?? null;
			if (
				$current !== null
				&& $current['valid']
				&& $current['signature'] === $this->signature($source, $current['definition'])
			) {
				continue;
			}
			if ($current !== null) {
				$this->db->titleSort->drop(['index' => $current['identifier']])->run();
				$dropped++;
			}
			$this->db->titleSort->create(['index' => $name, 'expression' => $definition['expression']])->run();
			$current = $this->existing()[$name];
			$this->db->titleSort->mark([
				'index' => $current['identifier'],
				// COMMENT cannot bind a parameter. Both hashes are generated here.
				'signature' => $this->db->quote($this->signature($source, $current['definition'])),
			])->run();
			$created++;
		}

		foreach (array_diff_key($existing, $wanted) as $index) {
			$this->db->titleSort->drop(['index' => $index['identifier']])->run();
			$dropped++;
		}

		return ['created' => $created, 'dropped' => $dropped, 'locales' => array_column($wanted, 'locale')];
	}

	private function existing(): array
	{
		return array_column($this->db->titleSort->indexes()->all(), null, 'name');
	}

	private function signature(string $source, string $definition): string
	{
		// Include PostgreSQL's actual definition so manual replacements are
		// detected without attempting to normalize its expression deparser.
		return 'cosray-title-sort:' . $source . ':' . hash('sha256', $definition);
	}
}
