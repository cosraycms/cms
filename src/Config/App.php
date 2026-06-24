<?php

declare(strict_types=1);

namespace Cosray\Config;

use DateTimeZone;

final class App
{
	private ?DateTimeZone $timezoneCache = null;

	public function __construct(
		private readonly \Cosray\Config $config,
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

	public DateTimeZone $timezone {
		get => $this->timezoneCache ??= new DateTimeZone($this->config->get('app.timezone'));
	}

	/** @var ?non-empty-string */
	public ?string $secret {
		get => $this->config->get('app.secret');
	}
}
