<?php

declare(strict_types=1);

namespace Cosray\Config;

use Cosray\Exception\InvalidEnvironment;

use function Cosray\env;

final class Env
{
	/** @var list<string> */
	private const BOOLEAN = ['APP_DEBUG', 'SITE_SESSION_ENABLED', 'SESSION_COOKIE_SECURE'];

	/** @var list<string> */
	private const INTEGER = [
		'AUTH_REMEMBER_LIFETIME',
		'SESSION_COOKIE_LIFETIME',
		'SESSION_IDLE_TIMEOUT',
	];

	public static function load(): self
	{
		return new self();
	}

	/** @param non-empty-string|list<non-empty-string> $variables */
	public function require(string|array $variables): void
	{
		$missing = array_filter((array) $variables, static fn(string $key): bool => env($key) === null);

		if ($missing !== []) {
			throw new InvalidEnvironment(
				'Missing required environment variable(s): ' . implode(', ', $missing),
			);
		}
	}

	public function validate(): void
	{
		foreach (self::BOOLEAN as $key) {
			$value = env($key);

			if ($value !== null && !self::isBoolean($value)) {
				throw new InvalidEnvironment("Environment variable {$key} must be a boolean.");
			}
		}

		foreach (self::INTEGER as $key) {
			$value = env($key);

			if ($value !== null && !self::isInteger($value)) {
				throw new InvalidEnvironment("Environment variable {$key} must be an integer.");
			}
		}
	}

	public function string(string $key, ?string $default = null): ?string
	{
		$value = env($key, $default);

		return $value === null ? null : (string) $value;
	}

	public function bool(string $key, bool $default): bool
	{
		return filter_var(env($key, $default), FILTER_VALIDATE_BOOL);
	}

	public function int(string $key, int $default): int
	{
		return (int) env($key, $default);
	}

	private static function isBoolean(mixed $value): bool
	{
		if (is_bool($value)) {
			return true;
		}

		return (
			is_string($value)
			&& in_array(strtolower($value), ['1', '0', 'true', 'false', 'on', 'off', 'yes', 'no'], true)
		);
	}

	private static function isInteger(mixed $value): bool
	{
		return is_int($value) || is_string($value) && ctype_digit($value);
	}
}
