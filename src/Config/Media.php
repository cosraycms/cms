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

	public Sizes $sizes {
		get => $this->sizesRegistry ??= new Sizes((array) $this->config->get('media.sizes'));
	}
}
