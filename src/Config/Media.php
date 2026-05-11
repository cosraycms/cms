<?php

declare(strict_types=1);

namespace Celemas\Cms\Config;

final class Media
{
	public function __construct(
		private readonly \Celemas\Cms\Config $config,
	) {}

	/** @var null|'apache'|'nginx' */
	public ?string $fileServer {
		get => $this->config->get('media.fileserver');
	}
}
