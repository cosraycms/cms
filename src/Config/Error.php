<?php

declare(strict_types=1);

namespace Cosray\Config;

use Celema\Core\Error\Renderer;

final class Error
{
	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	public bool $enabled {
		get => $this->config->get('error.enabled');
	}

	/** @var null|class-string<Renderer>|Renderer */
	public string|Renderer|null $renderer {
		get => $this->config->get('error.renderer');
	}

	/** @var list<class-string> */
	public array $trusted {
		get => $this->config->get('error.trusted');
	}

	/** @var null|non-empty-string|list<non-empty-string> */
	public string|array|null $views {
		get => $this->config->get('error.views');
	}

	public bool $whoops {
		get => $this->config->get('error.whoops');
	}
}
