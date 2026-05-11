<?php

declare(strict_types=1);

namespace Celemas\Cms\Config;

final class Path
{
	public function __construct(
		private readonly \Celemas\Cms\Config $config,
	) {}

	/** @var non-empty-string */
	public string $root {
		get => $this->config->get('path.root');
	}

	/** @var non-empty-string */
	public string $public {
		get => $this->config->get('path.public');
	}

	public string $prefix {
		get => $this->config->get('path.prefix');
	}

	/** @var non-empty-string */
	public string $assets {
		get => $this->config->get('path.assets');
	}

	/** @var non-empty-string */
	public string $cache {
		get => $this->config->get('path.cache');
	}

	/** @var non-empty-string */
	public string $views {
		get => $this->config->get('path.views');
	}

	/** @var non-empty-string */
	public string $panel {
		get => $this->config->get('path.panel');
	}

	/** @var ?non-empty-string */
	public ?string $api {
		get => $this->config->get('path.api');
	}
}
