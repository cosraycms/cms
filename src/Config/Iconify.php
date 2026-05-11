<?php

declare(strict_types=1);

namespace Celemas\Cms\Config;

final class Iconify
{
	public function __construct(
		private readonly \Celemas\Cms\Config $config,
	) {}

	/** @var non-empty-string */
	public string $baseUrl {
		get => $this->config->get('icons.iconify.base_url');
	}

	/** @var positive-int */
	public int $timeout {
		get => $this->config->get('icons.iconify.timeout');
	}

	/** @var non-empty-string */
	public string $userAgent {
		get => $this->config->get('icons.iconify.user_agent');
	}
}
