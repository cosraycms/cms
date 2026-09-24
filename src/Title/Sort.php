<?php

declare(strict_types=1);

namespace Cosray\Title;

use Celema\Quma\Database;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Field;
use Cosray\Locale;

/** Shared title expressions and collation selection for queries and indexes. */
final class Sort
{
	/** @var array<string, string>|null */
	private ?array $collations = null;

	public function __construct(
		private readonly Database $db,
	) {}

	public function order(Locale $locale, string $column = 'title'): string
	{
		$this->collations ??= array_column($this->db->titleSort->collations()->all(), 'identifier', 'name');
		$collation = self::chooseCollation($locale->id, $this->collations);
		$expression = self::expression($locale, $column);

		return $collation === null ? $expression : "({$expression}) COLLATE {$collation}";
	}

	/** @param array<string, string> $available Catalog names mapped to quoted SQL identifiers. */
	public static function chooseCollation(string $localeId, array $available): ?string
	{
		return $available[self::collation($localeId)] ?? $available['und-x-icu'] ?? null;
	}

	/** Match Resolver::stored(), including locale fallback and whitespace-only titles. */
	public static function expression(Locale|string $locale, string $column = 'title'): string
	{
		$ids = $locale instanceof Locale ? [$locale->id, ...$locale->fallbacks()] : [$locale];
		$ids[] = Field::NEUTRAL_LOCALE;
		$values = [];

		foreach (array_unique($ids) as $id) {
			if (!self::valid($id)) {
				throw new RuntimeException("Invalid title sort locale '{$id}'");
			}

			$value = "{$column}->>'{$id}'";
			$values[] =
				"CASE WHEN jsonb_typeof({$column}->'{$id}') = 'string' "
				. "AND BTRIM({$value}, E' \\t\\n\\r\\013') <> '' THEN {$value} END";
		}

		return 'COALESCE(' . implode(', ', $values) . ')';
	}

	public static function collation(string $localeId): string
	{
		return str_replace('_', '-', $localeId) . '-x-icu';
	}

	public static function indexName(string $localeId): string
	{
		if (!self::valid($localeId)) {
			throw new RuntimeException("Invalid title sort locale '{$localeId}'");
		}

		return 'ix_nodes_title_' . str_replace('-', '_', $localeId);
	}

	public static function valid(string $localeId): bool
	{
		return preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $localeId) === 1;
	}
}
