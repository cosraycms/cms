<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Cosray\Exception\ParserException;

trait CompilesField
{
	/** @param list<string> $localeIds */
	protected function compileField(
		string $fieldName,
		string $tableField,
		bool $asIs = false,
		array $localeIds = [],
	): string {
		$parts = explode('.', $fieldName);

		foreach ($parts as $part) {
			if ($part === '') {
				throw new ParserException('Invalid field name');
			}
		}

		$field = $parts[0];
		$localeIds = $this->normalizeLocaleIds($localeIds);
		$arrow = $asIs ? '->' : '->>';

		if (count($parts) === 1) {
			return $this->effectiveFieldExpression($tableField, $field, $localeIds, $asIs);
		}

		if (count($parts) === 2) {
			$accessor = $parts[1];

			if ($accessor === '?') {
				return "{$tableField}->'{$field}'->'value'{$arrow}'{$localeIds[0]}'";
			}

			if ($accessor === '*') {
				return "{$tableField}->'{$field}'->'value'";
			}

			return "{$tableField}->'{$field}'->'value'{$arrow}'{$accessor}'";
		}

		$path = "{$tableField}->'{$field}'";

		foreach (array_slice($parts, 1, -1) as $part) {
			$path .= "->'{$part}'";
		}

		$end = array_slice($parts, -1)[0];

		return "{$path}{$arrow}'{$end}'";
	}

	/** @param list<string> $localeIds */
	protected function effectiveFieldExpression(
		string $tableField,
		string $field,
		array $localeIds,
		bool $asIs,
	): string {
		$expressions = [];

		foreach ($localeIds as $localeId) {
			$operator = $asIs ? '->' : '->>';
			$expression = "{$tableField}->'{$field}'->'value'{$operator}'{$localeId}'";
			$expressions[] = $asIs ? $expression : "NULLIF({$expression}, '')";
		}

		return 'COALESCE(' . implode(', ', $expressions) . ')';
	}

	/** @param list<string> $localeIds */
	protected function normalizeLocaleIds(array $localeIds): array
	{
		$localeIds = array_values(array_filter($localeIds, static fn(string $id): bool => $id !== ''));

		if ($localeIds === []) {
			$localeIds = ['zxx'];
		}

		if (!in_array('zxx', $localeIds, true)) {
			$localeIds[] = 'zxx';
		}

		return array_values(array_unique($localeIds));
	}
}
