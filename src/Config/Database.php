<?php

declare(strict_types=1);

namespace Cosray\Config;

use Cosray\Exception\RuntimeException;

final class Database
{
	/** @var list<non-empty-string>|null */
	private ?array $sqlCache = null;

	/** @var list<non-empty-string>|null */
	private ?array $migrationsCache = null;

	/** @var array<string, mixed>|null */
	private ?array $optionsCache = null;

	/** @var array<non-empty-string, array<non-empty-string, string>>|null */
	private ?array $placeholdersCache = null;

	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	/** @var ?non-empty-string */
	public ?string $dsn {
		get => $this->config->get('db.dsn');
	}

	/** @var list<non-empty-string> */
	public array $sql {
		get => $this->sqlCache ??= self::strings($this->config->get('db.sql'));
	}

	/** @var list<non-empty-string> */
	public array $migrations {
		get => $this->migrationsCache ??= self::strings($this->config->get('db.migrations'));
	}

	/** @var array<non-empty-string, array<non-empty-string, string>> */
	public array $placeholders {
		get => $this->placeholdersCache ??= $this->validatedPlaceholders();
	}

	public bool $print {
		get => $this->config->get('db.print');
	}

	/** @var array<string, mixed> */
	public array $options {
		get => $this->optionsCache ??= $this->config->get('db.options');
	}

	public function table(string $name, ?string $driver = null): string
	{
		if (preg_match('/^[a-z_][a-z0-9_]*$/', $name) !== 1) {
			throw new RuntimeException('Invalid table name.');
		}

		return $this->prefix($driver) . $name;
	}

	/** @return array<non-empty-string, array<non-empty-string, string>> */
	private function validatedPlaceholders(): array
	{
		/** @var array<non-empty-string, array<non-empty-string, string>> $placeholders */
		$placeholders = $this->config->get('db.placeholders');
		$driver = $this->driver();
		$prefix = $placeholders[$driver]['cms.prefix'] ?? null;

		if ($prefix !== null) {
			$this->assertValidPrefix($prefix, $driver);

			return $this->withPostgresqlObjectPrefix($placeholders);
		}

		$prefix = $placeholders['all']['cms.prefix'] ?? null;

		if ($prefix !== null) {
			$this->assertValidPrefix($prefix, $driver);

			return $this->withPostgresqlObjectPrefix($placeholders);
		}

		throw new RuntimeException('Invalid table prefix.');
	}

	/**
	 * @param array<non-empty-string, array<non-empty-string, string>> $placeholders
	 *
	 * @return array<non-empty-string, array<non-empty-string, string>>
	 */
	private function withPostgresqlObjectPrefix(array $placeholders): array
	{
		if (array_key_exists('cms.obj', $placeholders['pgsql'] ?? [])) {
			return $placeholders;
		}

		$prefix = $placeholders['pgsql']['cms.prefix'] ?? $placeholders['all']['cms.prefix'] ?? null;

		if ($prefix === null) {
			return $placeholders;
		}

		if (!is_string($prefix)) {
			throw new RuntimeException('Invalid table prefix.');
		}

		$this->assertValidPrefix($prefix, 'pgsql');

		$placeholders['pgsql']['cms.obj'] = str_ends_with($prefix, '.') ? '' : $prefix;

		return $placeholders;
	}

	private function prefix(?string $driver = null): string
	{
		$placeholders = $this->placeholders;
		$driver ??= $this->driver();
		$prefix = $placeholders[$driver]['cms.prefix'] ?? $placeholders['all']['cms.prefix'] ?? null;

		if (!is_string($prefix)) {
			throw new RuntimeException('Invalid table prefix.');
		}

		$this->assertValidPrefix($prefix, $driver);

		return $prefix;
	}

	private function assertValidPrefix(mixed $prefix, string $driver): void
	{
		if (is_string($prefix) && $this->validPrefix($prefix, $driver)) {
			return;
		}

		throw new RuntimeException('Invalid table prefix.');
	}

	private function validPrefix(string $prefix, string $driver): bool
	{
		if ($driver === 'pgsql') {
			return preg_match('/^(?:[a-z_][a-z0-9_]*[.]?)?$/', $prefix) === 1;
		}

		return preg_match('/^(?:[a-z_][a-z0-9_]*)?$/', $prefix) === 1;
	}

	private function driver(): string
	{
		$driver = strstr($this->dsn ?? '', ':', before_needle: true);

		return $driver === false ? '' : $driver;
	}

	/** @return list<non-empty-string> */
	private static function strings(mixed $value): array
	{
		if ($value === null) {
			return [];
		}

		if (is_string($value)) {
			$value = trim($value);

			return $value === '' ? [] : [$value];
		}

		return array_values($value);
	}
}
