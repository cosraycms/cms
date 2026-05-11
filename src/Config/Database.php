<?php

declare(strict_types=1);

namespace Celemas\Cms\Config;

final class Database
{
	/** @var list<non-empty-string>|null */
	private ?array $sqlCache = null;

	/** @var list<non-empty-string>|null */
	private ?array $migrationsCache = null;

	/** @var array<string, mixed>|null */
	private ?array $optionsCache = null;

	public function __construct(
		private readonly \Celemas\Cms\Config $config,
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

	public bool $print {
		get => $this->config->get('db.print');
	}

	/** @var array<string, mixed> */
	public array $options {
		get => $this->optionsCache ??= $this->config->get('db.options');
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
