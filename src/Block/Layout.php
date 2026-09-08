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
	public const int MAX_ROWSPAN = 6;

	public function __construct(
		public int $colspan,
		public int $rowspan,
		public int $indent,
	) {}

	public static function normalize(mixed $layout, int $columns, int $min): self
	{
		$layout = is_array($layout) ? $layout : [];
		$colspan = self::clamp(self::int($layout['colspan'] ?? null, $columns), $min, $columns);
		$rowspan = self::clamp(self::int($layout['rowspan'] ?? null, 1), 1, self::MAX_ROWSPAN);
		$indent = self::clamp(self::int($layout['indent'] ?? null, 0), 0, $columns - $colspan);

		return new self($colspan, $rowspan, $indent);
	}

	/** @return array{colspan: int, rowspan: int, indent: int} */
	public function array(): array
	{
		return ['colspan' => $this->colspan, 'rowspan' => $this->rowspan, 'indent' => $this->indent];
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
