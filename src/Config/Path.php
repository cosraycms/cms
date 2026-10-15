<?php

declare(strict_types=1);

namespace Cosray\Config;

final class Path
{
	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	public string $root {
		get => $this->config->get('path.root');
	}

	public string $public {
		get => $this->config->get('path.public');
	}

	public string $assets {
		get => $this->config->get('path.assets');
	}

	public string $cache {
		get => $this->config->get('path.cache');
	}

	public string $views {
		get => $this->config->get('path.views');
	}
}
