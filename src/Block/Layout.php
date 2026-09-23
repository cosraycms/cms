<?php

declare(strict_types=1);

namespace Cosray\Block;

/**
 * A block's grid placement: the columns and rows it spans and, for a
 * block on the field's grid, the column and row it starts at (1-based).
 * A block without a position gets one from Placement; the blocks of a
 * split have none, they are laid out on the split's area in order: its
 * colspan is their column count, its rowspan their row limit. Readers
 * clamp stored values so a field narrowed later or an out-of-range
 * import never breaks a render; the same bounds are enforced on save.
 */
final readonly class Layout
{
	public const int MAX_ROWSPAN = 6;

	public function __construct(
		public int $colspan,
		public int $rowspan,
		public int $col = 0,
		public int $row = 0,
	) {}

	public static function normalize(mixed $layout, int $columns, int $min, int $rows = self::MAX_ROWSPAN): self
	{
		$layout = is_array($layout) ? $layout : [];
		$colspan = self::clamp(self::int($layout['colspan'] ?? null, $columns), min($min, $columns), $columns);
		$rowspan = self::clamp(self::int($layout['rowspan'] ?? null, 1), 1, $rows);
		$col = self::int($layout['col'] ?? null, 0);
		$row = self::int($layout['row'] ?? null, 0);

		if ($col < 1 || $row < 1) {
			return new self($colspan, $rowspan);
		}

		return new self($colspan, $rowspan, self::clamp($col, 1, $columns - $colspan + 1), $row);
	}

	public function placed(): bool
	{
		return $this->col > 0;
	}

	public function at(int $col, int $row): self
	{
		return new self($this->colspan, $this->rowspan, $col, $row);
	}

	/** @return array{colspan: int, rowspan: int, col?: int, row?: int} */
	public function array(): array
	{
		$layout = ['colspan' => $this->colspan, 'rowspan' => $this->rowspan];

		return $this->placed() ? [...$layout, 'col' => $this->col, 'row' => $this->row] : $layout;
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
