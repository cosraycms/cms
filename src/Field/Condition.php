<?php

declare(strict_types=1);

namespace Cosray\Field;

/**
 * Evaluates a When condition against stored node content. The editor
 * behavior evaluates the identical condition against form state — the
 * two implementations must stay in lockstep, which is why the value
 * normalization mirrors form semantics (bools become '1'/'', scalars
 * become strings).
 */
final class Condition
{
	/** @param array{field: string, op: string, value: mixed} $condition */
	public static function active(array $condition, array $content): bool
	{
		$raw = $content[$condition['field']]['value'][Field::NEUTRAL_LOCALE] ?? null;
		$value = self::normalize($raw);

		return match ($condition['op']) {
			'truthy' => $value !== '' && $value !== '0',
			'eq' => $value === self::normalize($condition['value']),
			'neq' => $value !== self::normalize($condition['value']),
			'in' => in_array(
				$value,
				array_map(self::normalize(...), is_array($condition['value']) ? $condition['value'] : []),
				true,
			),
			'empty' => $value === '',
			'notEmpty' => $value !== '',
			default => true,
		};
	}

	private static function normalize(mixed $value): string
	{
		if (is_bool($value)) {
			return $value ? '1' : '';
		}

		return is_scalar($value) ? (string) $value : '';
	}
}
