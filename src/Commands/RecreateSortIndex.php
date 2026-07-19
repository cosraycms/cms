<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Quma\Connection;
use Celema\Quma\Database;
use Cosray\Field\Field;
use Cosray\Title\Sort;

/**
 * Reconciles the per-locale node title sort indexes against the locales that
 * actually appear in `nodes.title`. Data-driven on purpose: it needs no app
 * boot, and adding a locale only requires a re-run once its first title
 * exists. One collated expression index per locale key backs the locale-aware
 * `ORDER BY` the panel uses.
 */
#[Command(
	'db:recreate-sort-index',
	'Rebuilds the per-locale node title sort indexes',
	group: 'Database',
)]
class RecreateSortIndex
{
	private readonly Database $db;

	public function __construct(
		private readonly Connection $conn,
	) {
		$this->db = new Database($conn);
	}

	public function __invoke(Args $args, Io $io): int
	{
		$locales = $this->locales();
		$existing = $this->existingIndexes();

		$want = [];

		foreach ($locales as $locale) {
			$want[Sort::indexName($locale)] = $locale;
		}

		$created = 0;

		foreach ($want as $index => $locale) {
			if (in_array($index, $existing, true)) {
				continue;
			}

			$this->create($locale);
			$created++;
		}

		$dropped = 0;

		foreach ($existing as $index) {
			if (array_key_exists($index, $want)) {
				continue;
			}

			$this->drop($index);
			$dropped++;
		}

		echo
			"Title sort indexes reconciled: {$created} created, {$dropped} dropped, "
				. count($want)
				. ' locale(s) ['
				. implode(', ', array_values($want))
				. "]\n"
		;

		return 0;
	}

	/**
	 * Distinct, sortable locale keys present in stored titles (never the
	 * neutral key — it is the fallback inside every locale's index).
	 *
	 * @return list<string>
	 */
	private function locales(): array
	{
		$sql = <<<'SQL'
			SELECT DISTINCT jsonb_object_keys(title) AS locale
			FROM /*:cms.prefix:*/nodes
			WHERE title <> '{}'::jsonb
			SQL;

		$rows = $this->db->execute($this->apply($sql))->all();
		$locales = [];

		foreach ($rows as $row) {
			$locale = (string) $row['locale'];

			if ($locale !== Field::NEUTRAL_LOCALE && Sort::valid($locale)) {
				$locales[] = $locale;
			}
		}

		sort($locales);

		return $locales;
	}

	/**
	 * Existing title sort index names.
	 *
	 * @return list<string>
	 */
	private function existingIndexes(): array
	{
		$sql = <<<'SQL'
			SELECT indexname
			FROM pg_indexes
			WHERE tablename = 'nodes' AND indexname LIKE 'ix_nodes_title_%'
			SQL;

		$rows = $this->db->execute($sql)->all();

		return array_map(static fn(array $row): string => (string) $row['indexname'], $rows);
	}

	private function create(string $locale): void
	{
		$expression = Sort::expression($locale);
		$collation = $this->collation($locale);
		$collate = $collation !== null ? " COLLATE \"{$collation}\"" : '';
		$index = Sort::indexName($locale);

		$sql =
			"CREATE INDEX IF NOT EXISTS {$index} " . "ON /*:cms.prefix:*/nodes (({$expression}){$collate})";

		$this->db->execute($this->apply($sql))->run();
	}

	private function drop(string $index): void
	{
		$this->db->execute($this->apply("DROP INDEX IF EXISTS /*:cms.prefix:*/{$index}"))->run();
	}

	/**
	 * The ICU collation for a locale if the server has it, else the ICU root,
	 * else none (a non-ICU build still sorts, just byte-wise).
	 */
	private function collation(string $locale): ?string
	{
		foreach ([Sort::collation($locale), 'und-x-icu'] as $candidate) {
			$found = $this->db
				->execute(
					'SELECT 1 FROM pg_collation WHERE collname = :name',
					['name' => $candidate],
				)
				->all();

			if ($found !== []) {
				return $candidate;
			}
		}

		return null;
	}

	private function apply(string $sql): string
	{
		return $this->conn->config->placeholders?->compileSql($sql, __FILE__) ?? $sql;
	}
}
