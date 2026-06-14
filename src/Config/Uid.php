<?php

declare(strict_types=1);

namespace Cosray\Config;

final class Uid
{
	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	/** @var non-empty-string */
	public string $alphabet {
		get => $this->config->get('uid.alphabet');
	}

	/** @var positive-int */
	public int $length {
		get => $this->config->get('uid.length');
	}
}
