<?php

declare(strict_types=1);

namespace Cosray\Migration;

use Cosray\Block\Layout;
use Cosray\Block\Placement;
use Cosray\Field\Blocks;
use Cosray\Field\Definitions;
use Cosray\Schema\Columns;

/**
 * Blocks content from before positions: each row list flowed, the
 * browser's sparse row flow placing spans after an optional indent. The
 * conversion places every top-level row where that flow put it, stores
 * the grid the rows were laid out on next to them and drops the indent,
 * from the rows and from the blocks of their splits. Spans are clamped
 * the way the readers clamped them, so a row keeps the size it rendered
 * with. A list that already holds a position is left placed as it is,
 * which makes the conversion safe to run again.
 */
final class BlockPositions
{
	/**
	 * The grid of every Blocks field of a node class: `#[Columns]`, one
	 * column without it.
	 *
	 * @param class-string $class
	 * @return array<string, array{columns: int, min: int}>
	 */
	public static function grids(string $class): array
	{
		$grids = [];

		foreach (Definitions::for($class)->fields() as $name => $definition) {
			if (!is_a($definition->type, Blocks::class, true)) {
				continue;
			}

			$attribute = $definition->property->getAttributes(Columns::class)[0] ?? null;
			$columns = $attribute?->newInstance();
			$grids[$name] = [
				'columns' => $columns?->columns ?? 1,
				'min' => $columns?->min ?? 1,
			];
		}

		return $grids;
	}

	/**
	 * @param array<string, mixed> $content
	 * @param array<string, array{columns: int, min: int}> $grids
	 * @return array<string, mixed>
	 */
	public static function content(array $content, array $grids): array
	{
		foreach ($grids as $name => $grid) {
			$field = $content[$name] ?? null;

			if (!is_array($field) || !is_array($field['value'] ?? null)) {
				continue;
			}

			$columns = Blocks::storedColumns($field['columns'] ?? null, $grid['columns']);

			foreach ($field['value'] as $locale => $rows) {
				if (is_array($rows) && array_is_list($rows)) {
					$field['value'][$locale] = self::rows($rows, $columns, $grid['min']);
				}
			}

			$content[$name] = [...$field, 'columns' => $columns];
		}

		return $content;
	}

	/**
	 * @param list<mixed> $rows
	 * @return list<mixed>
	 */
	public static function rows(array $rows, int $columns, int $min): array
	{
		$min = min($min, $columns);
		$placed = false;
		$spans = [];

		foreach ($rows as $row) {
			$layout = is_array($row) && is_array($row['layout'] ?? null) ? $row['layout'] : [];
			$placed = $placed || isset($layout['col'], $layout['row']);
		}

		foreach ($rows as $index => $row) {
			if (!is_array($row)) {
				continue;
			}

			$layout = is_array($row['layout'] ?? null) ? $row['layout'] : [];
			$colspan = self::clamp(self::int($layout['colspan'] ?? null, $columns), $min, $columns);
			$rowspan = self::clamp(self::int($layout['rowspan'] ?? null, 1), 1, Layout::MAX_ROWSPAN);
			$indent = self::clamp(self::int($layout['indent'] ?? null, 0), 0, $columns - $colspan);
			unset($layout['indent']);
			$rows[$index]['layout'] = [...$layout, 'colspan' => $colspan, 'rowspan' => $rowspan];

			if (is_array($row['blocks'] ?? null)) {
				$rows[$index]['blocks'] = array_map(self::part(...), $row['blocks']);
			}

			if (!isset($layout['col'], $layout['row'])) {
				$spans[$index] = ['colspan' => $colspan, 'rowspan' => $rowspan, 'indent' => $indent];
			}
		}

		// A list with a position was converted, or written since: its rows
		// without one are placed by the readers, below the others.
		if ($placed) {
			return $rows;
		}

		$spots = Placement::flow(array_values($spans), $columns);

		foreach (array_keys($spans) as $position => $index) {
			$rows[$index]['layout'] = [...$rows[$index]['layout'], ...$spots[$position]];
		}

		return $rows;
	}

	private static function part(mixed $part): mixed
	{
		if (is_array($part) && is_array($part['layout'] ?? null)) {
			unset($part['layout']['indent']);
		}

		return $part;
	}

	private static function int(mixed $value, int $default): int
	{
		return is_int($value) || is_numeric($value) ? (int) $value : $default;
	}

	private static function clamp(int $value, int $min, int $max): int
	{
		return max($min, min($max, $value));
	}
}
