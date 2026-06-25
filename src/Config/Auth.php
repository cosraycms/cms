<?php

declare(strict_types=1);

namespace Cosray\Config;

final class Auth
{
	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	public int $rememberLifetime {
		get => $this->config->get('auth.remember_lifetime');
	}
}
