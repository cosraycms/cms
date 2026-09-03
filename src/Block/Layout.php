<?php

declare(strict_types=1);

namespace Cosray\Block;

/**
 * A block's grid placement: the columns it spans, the rows it spans
 * and its offset from the row start. Readers clamp stored values so a
 * field narrowed later or an out-of-range import never breaks a render;
 * the same bounds are enforced on save by the field shape.
 */
final readonly class Layout
{
	public const int MAX_ROWS = 6;

	public function __construct(
		public int $span,
		public int $rows,
		public int $indent,
	) {}

	public static function normalize(mixed $layout, int $columns, int $min): self
	{
		$layout = is_array($layout) ? $layout : [];
		$span = self::clamp(self::int($layout['span'] ?? null, $columns), $min, $columns);
		$rows = self::clamp(self::int($layout['rows'] ?? null, 1), 1, self::MAX_ROWS);
		$indent = self::clamp(self::int($layout['indent'] ?? null, 0), 0, $columns - $span);

		return new self($span, $rows, $indent);
	}

	/** @return array{span: int, rows: int, indent: int} */
	public function array(): array
	{
		return ['span' => $this->span, 'rows' => $this->rows, 'indent' => $this->indent];
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
