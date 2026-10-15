<?php

declare(strict_types=1);

namespace Cosray\Config;

final class Password
{
	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	public float $entropy {
		get => $this->config->get('password.entropy');
	}

	public int|string|null $algorithm {
		get => $this->config->get('password.algorithm');
	}
}
