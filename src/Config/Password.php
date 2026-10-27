<?php

declare(strict_types=1);

namespace Cosray\Config;

final class Password
{
	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	/** Whether new passwords are looked up in Have I Been Pwned. */
	public bool $breachCheck {
		get => (bool) $this->config->get('password.breach_check');
	}
}
