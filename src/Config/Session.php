<?php

declare(strict_types=1);

namespace Celemas\Cms\Config;

use SessionHandlerInterface;

/** @psalm-import-type SessionOptions from Types */
final class Session
{
	/** @var SessionOptions|null */
	private ?array $optionsCache = null;

	public function __construct(
		private readonly \Celemas\Cms\Config $config,
	) {}

	public bool $enabled {
		get => $this->config->get('session.enabled');
	}

	/** @var SessionOptions */
	public array $options {
		get => $this->optionsCache ??= $this->config->get('session.options');
	}

	public ?SessionHandlerInterface $handler {
		get => $this->config->get('session.handler');
	}
}
