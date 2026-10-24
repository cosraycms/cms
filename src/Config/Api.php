<?php

declare(strict_types=1);

namespace Cosray\Config;

final class Api
{
	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	/** Where the API is mounted, like `/api`; null leaves it off. */
	public ?string $path {
		get => self::mount($this->config->get('api.path'));
	}

	private static function mount(mixed $path): ?string
	{
		if (!is_string($path) || trim($path, " \t/") === '') {
			return null;
		}

		return '/' . trim($path, " \t/");
	}
}
