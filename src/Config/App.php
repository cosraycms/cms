<?php

declare(strict_types=1);

namespace Celemas\Cms\Config;

final class App
{
	public function __construct(
		private readonly \Celemas\Cms\Config $config,
	) {}

	/** @var non-empty-string */
	public string $name {
		get => $this->config->get('app.name');
	}

	public bool $debug {
		get => $this->config->get('app.debug');
	}

	public string $env {
		get => $this->config->get('app.env');
	}

	/** @var ?non-empty-string */
	public ?string $secret {
		get => $this->config->get('app.secret');
	}
}
