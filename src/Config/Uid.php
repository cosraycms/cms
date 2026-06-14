<?php

declare(strict_types=1);

namespace Cosray\Config;

use Cosray\Uid as UidGenerator;

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

	public function create(): UidGenerator
	{
		return new UidGenerator($this->alphabet, $this->length);
	}
}
