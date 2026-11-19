<?php

declare(strict_types=1);

namespace Cosray\Config;

final class Maintenance
{
	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	/** The file whose presence turns maintenance mode on. */
	public string $file {
		get => (string) $this->config->get('maintenance.file');
	}

	/**
	 * Paths served as usual during maintenance, each with everything below it.
	 *
	 * @var list<non-empty-string>
	 */
	public array $allow {
		get => array_values(array_filter(
			array_map(
				static fn(mixed $path): string => is_string($path) ? '/' . trim($path, '/') : '',
				(array) $this->config->get('maintenance.allow'),
			),
			static fn(string $path): bool => $path !== '' && $path !== '/',
		));
	}
}
