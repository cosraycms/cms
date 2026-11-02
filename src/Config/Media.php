<?php

declare(strict_types=1);

namespace Cosray\Config;

use Cosray\Assets\Sizes;

final class Media
{
	private ?Sizes $sizesRegistry = null;

	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	/** @var null|'apache'|'nginx' */
	public ?string $fileServer {
		get => $this->config->get('media.fileserver');
	}

	/** What renders images: `gd`, or `imagick` where the extension is there. */
	public string $backend {
		get => (string) $this->config->get('media.backend');
	}

	public Sizes $sizes {
		get => $this->sizesRegistry ??= new Sizes((array) $this->config->get('media.sizes'), $this->backend);
	}
}
