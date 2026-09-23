<?php

declare(strict_types=1);

namespace Cosray\Block;

/**
 * Positions on a blocks grid. A block on the field's grid sits at the
 * column and row it starts at; the reading order follows from the
 * positions, row by row and left to right, and rows are kept in it. A block without a position
 * (an import, a programmatic write) is placed below the placed ones,
 * flowing in order the way the browser's sparse row flow places spans.
 * The same flow gave the blocks of a grid saved before positions
 * existed their place, indent included.
 */
final class Placement
{
	/**
	 * Where the sparse row flow puts each block: at the first spot from
	 * the cursor where its indent and span fit, the box past the indent.
	 * Spans wider than the grid are narrowed to it.
	 *
	 * @param list<array{colspan: int, rowspan: int, indent?: int}> $spans
	 * @return list<array{col: int, row: int}>
	 */
	public static function flow(array $spans, int $columns): array
	{
		$columns = max(1, $columns);
		$taken = [];
		[$top, $left] = [0, 0];
		$result = [];

		foreach ($spans as $span) {
			$colspan = max(1, min($columns, $span['colspan']));
			$rowspan = max(1, $span['rowspan']);
			$width = min($columns, $colspan + max(0, $span['indent'] ?? 0));

			for ($row = $top, $col = $left;; $row++, $col = 0) {
				for (; ($col + $width) <= $columns; $col++) {
					if (self::free($taken, $row, $col, $width, $rowspan)) {
						break 2;
					}
				}
			}

			for ($r = $row; $r < ($row + $rowspan); $r++) {
				for ($c = $col; $c < ($col + $width); $c++) {
					$taken["{$r}:{$c}"] = true;
				}
			}

			[$top, $left] = [$row, $col + $width];
			$result[] = ['col' => $col + $width - $colspan + 1, 'row' => $row + 1];
		}

		return $result;
	}

	/**
	 * Every layout with a position: placed ones keep theirs, the others
	 * flow below them in order.
	 *
	 * @param list<Layout> $layouts
	 * @return list<Layout>
	 */
	public static function place(array $layouts, int $columns): array
	{
		$bottom = 0;
		$loose = [];

		foreach ($layouts as $index => $layout) {
			if ($layout->placed()) {
				$bottom = max($bottom, $layout->row + $layout->rowspan - 1);
			} else {
				$loose[$index] = ['colspan' => $layout->colspan, 'rowspan' => $layout->rowspan];
			}
		}

		$spots = self::flow(array_values($loose), $columns);

		foreach (array_keys($loose) as $position => $index) {
			$layouts[$index] = $layouts[$index]->at($spots[$position]['col'], $bottom + $spots[$position]['row']);
		}

		return $layouts;
	}

	/**
	 * The index of the first layout overlapping one before it, null when
	 * none do. Layouts without a position take no cells.
	 *
	 * @param list<Layout> $layouts
	 */
	public static function overlap(array $layouts): ?int
	{
		foreach ($layouts as $index => $layout) {
			for ($other = 0; $other < $index; $other++) {
				if (self::overlaps($layouts[$other], $layout)) {
					return $index;
				}
			}
		}

		return null;
	}

	/**
	 * Stored rows with every top-level layout clamped to the grid and
	 * placed, in reading order; rows that are no arrays go last.
	 *
	 * @param array<array-key, mixed> $rows
	 * @return list<mixed>
	 */
	public static function rows(array $rows, int $columns, int $min): array
	{
		$rows = array_values($rows);
		$layouts = [];

		foreach ($rows as $index => $row) {
			if (is_array($row)) {
				$layouts[$index] = Layout::normalize($row['layout'] ?? null, $columns, $min);
			}
		}

		$placed = array_combine(array_keys($layouts), self::place(array_values($layouts), $columns));
		$order = array_keys($rows);

		usort($order, static fn(int $a, int $b): int => isset($placed[$a], $placed[$b])
			? [$placed[$a]->row, $placed[$a]->col, $a] <=> [$placed[$b]->row, $placed[$b]->col, $b]
			: [isset($placed[$b]), $a] <=> [isset($placed[$a]), $b]);

		return array_map(static function (int $index) use ($rows, $placed): mixed {
			$row = $rows[$index];

			if (isset($placed[$index]) && is_array($row)) {
				$row['layout'] = $placed[$index]->array();
			}

			return $row;
		}, $order);
	}

	private static function overlaps(Layout $a, Layout $b): bool
	{
		return (
			$a->placed()
				&& $b->placed()
				&& $a->col < ($b->col + $b->colspan)
				&& $b->col < ($a->col + $a->colspan)
				&& $a->row < ($b->row + $b->rowspan)
				&& $b->row < ($a->row + $a->rowspan)
		);
	}

	/** @param array<string, true> $taken */
	private static function free(array $taken, int $top, int $left, int $width, int $height): bool
	{
		for ($row = $top; $row < ($top + $height); $row++) {
			for ($col = $left; $col < ($left + $width); $col++) {
				if (isset($taken["{$row}:{$col}"])) {
					return false;
				}
			}
		}

		return true;
	}
}
