<?php

declare(strict_types=1);

namespace Cosray\Config;

use Dotenv\Dotenv;

use function Cosray\env;

final class Env
{
	private function __construct(
		private readonly Dotenv $dotenv,
	) {}

	public static function load(string $root): self
	{
		$dotenv = Dotenv::createImmutable($root);
		$dotenv->safeLoad();

		return new self($dotenv);
	}

	/** @param non-empty-string|list<non-empty-string> $variables */
	public function require(string|array $variables): void
	{
		$this->dotenv->required($variables);
	}

	public function validate(): void
	{
		$this->dotenv->ifPresent([
			'APP_DEBUG',
			'SITE_SESSION_ENABLED',
			'SESSION_COOKIE_SECURE',
		])->isBoolean();

		$this->dotenv->ifPresent([
			'AUTH_REMEMBER_LIFETIME',
			'SESSION_COOKIE_LIFETIME',
			'SESSION_IDLE_TIMEOUT',
		])->isInteger();
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
}
