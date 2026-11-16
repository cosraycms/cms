<?php

declare(strict_types=1);

namespace Cosray\Text;

use Cosray\Exception\RuntimeException;

/**
 * RFC 6901 JSON pointers into stored node content.
 *
 * @api
 */
final class Pointer
{
	/** @param list<string|int> $segments */
	public static function to(array $segments): string
	{
		return implode('', array_map(
			static fn(string|int $segment): string => '/' . strtr((string) $segment, ['~' => '~0', '/' => '~1']),
			$segments,
		));
	}

	/** @return list<string> */
	public static function segments(string $pointer): array
	{
		if ($pointer === '') {
			return [];
		}

		if (!str_starts_with($pointer, '/')) {
			throw new RuntimeException("Invalid JSON pointer '{$pointer}'");
		}

		return array_map(
			static fn(string $segment): string => strtr($segment, ['~1' => '/', '~0' => '~']),
			explode('/', substr($pointer, 1)),
		);
	}

	/**
	 * @param array<mixed> $data
	 * @return array{found: bool, value: mixed}
	 */
	public static function get(array $data, string $pointer): array
	{
		$value = $data;

		foreach (self::segments($pointer) as $segment) {
			if (!is_array($value) || !array_key_exists($segment, $value)) {
				return ['found' => false, 'value' => null];
			}

			$value = $value[$segment];
		}

		return ['found' => true, 'value' => $value];
	}

	/**
	 * Replaces the value an existing pointer names.
	 *
	 * @param array<mixed> $data
	 * @return array<mixed>
	 */
	public static function set(array $data, string $pointer, mixed $value): array
	{
		$segments = self::segments($pointer);

		if ($segments === []) {
			return is_array($value) ? $value : throw new RuntimeException('The root must stay an array');
		}

		$target = &$data;

		foreach ($segments as $segment) {
			if (!is_array($target) || !array_key_exists($segment, $target)) {
				throw new RuntimeException("Nothing at JSON pointer '{$pointer}'");
			}

			$target = &$target[$segment];
		}

		$target = $value;

		return $data;
	}
}
