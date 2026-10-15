<?php

declare(strict_types=1);

namespace Cosray\Config;

final class Iconify
{
	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	public string $baseUrl {
		get => $this->config->get('icons.iconify.base_url');
	}

	public int $timeout {
		get => $this->config->get('icons.iconify.timeout');
	}

	public string $userAgent {
		get => $this->config->get('icons.iconify.user_agent');
	}
}
