<?php

declare(strict_types=1);

namespace Cosray\Title;

use Cosray\Field\Field;

/**
 * Canonical SQL for ordering nodes by their materialized title in a given
 * locale. The same expression backs the per-locale sort indexes and the
 * queries that use them, so an `ORDER BY` matches its index exactly.
 *
 * Locale ids are inlined into the SQL (they are trusted config values, like
 * the field read path in Finder\CompilesField), so every id passes through
 * {@see self::valid()} first.
 */
class Sort
{
	/**
	 * Sort key for one locale: the title in that locale, falling back to the
	 * neutral key, with blanks treated as absent.
	 */
	public static function expression(string $localeId, string $column = 'title'): string
	{
		return sprintf(
			"COALESCE(NULLIF(%s->>'%s', ''), NULLIF(%s->>'%s', ''))",
			$column,
			$localeId,
			$column,
			Field::NEUTRAL_LOCALE,
		);
	}

	/**
	 * ICU collation name for locale-correct ordering, e.g. `de-x-icu`.
	 */
	public static function collation(string $localeId): string
	{
		return $localeId . '-x-icu';
	}

	/**
	 * Unprefixed index name for a locale's sort index.
	 */
	public static function indexName(string $localeId): string
	{
		return 'ix_nodes_title_' . str_replace('-', '_', $localeId);
	}

	/**
	 * A locale id safe to inline into SQL and to use in an identifier.
	 */
	public static function valid(string $localeId): bool
	{
		return preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $localeId) === 1;
	}
}
