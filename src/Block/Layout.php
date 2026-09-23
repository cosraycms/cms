<?php

declare(strict_types=1);

namespace Cosray\Block;

/**
 * A block's grid placement: the columns it spans, the rows it spans
 * and its offset from the row start. A placed block also carries the
 * line it starts at on both axes (1-based, 0 while it only flows);
 * its indent is then 0 and the position says where it sits. Readers
 * clamp stored values so a field narrowed later or an out-of-range
 * import never breaks a render; the same bounds are enforced on save by
 * the field shape. The blocks
 * of a split are placed on the split's area: its colspan is their
 * column count, its rowspan their row limit.
 */
final readonly class Layout
{
	public const int MAX_ROWSPAN = 6;

	public function __construct(
		public int $colspan,
		public int $rowspan,
		public int $indent,
		public int $col = 0,
		public int $row = 0,
	) {}

	public static function normalize(mixed $layout, int $columns, int $min, int $rows = self::MAX_ROWSPAN): self
	{
		$layout = is_array($layout) ? $layout : [];
		$colspan = self::clamp(self::int($layout['colspan'] ?? null, $columns), $min, $columns);
		$rowspan = self::clamp(self::int($layout['rowspan'] ?? null, 1), 1, $rows);
		$indent = self::clamp(self::int($layout['indent'] ?? null, 0), 0, $columns - $colspan);
		$col = self::int($layout['col'] ?? null, 0);
		$row = self::int($layout['row'] ?? null, 0);

		if ($col < 1 || $row < 1) {
			return new self($colspan, $rowspan, $indent);
		}

		return new self($colspan, $rowspan, 0, self::clamp($col, 1, $columns - $colspan + 1), $row);
	}

	public function placed(): bool
	{
		return $this->col > 0;
	}

	/** @return array{colspan: int, rowspan: int, indent: int, col?: int, row?: int} */
	public function array(): array
	{
		$layout = ['colspan' => $this->colspan, 'rowspan' => $this->rowspan, 'indent' => $this->indent];

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
