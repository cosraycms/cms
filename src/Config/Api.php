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

	/** @var list<string> */
	public array $origins {
		get => array_values(array_filter(
			(array) $this->config->get('api.origins'),
			static fn(mixed $origin): bool => is_string($origin) && $origin !== '',
		));
	}

	public int $maxAge {
		get => max(0, (int) $this->config->get('api.max_age'));
	}

	/** A headless frontend's preview URL with `{uid}`, `{token}` and `{locale}` placeholders. */
	public ?string $preview {
		get => is_string($this->config->get('api.preview')) && $this->config->get('api.preview') !== ''
			? $this->config->get('api.preview')
			: null;
	}

	private static function mount(mixed $path): ?string
	{
		if (!is_string($path) || trim($path, " \t/") === '') {
			return null;
		}

		return '/' . trim($path, " \t/");
	}
}
