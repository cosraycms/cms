<?php

declare(strict_types=1);

namespace Celemas\Cms;

use Celemas\Core\App;
use Celemas\Core\Factory\Factory;

/** @psalm-api */
final class PanelRenderers
{
	private Factory $factory;
	private Renderer $renderer;

	public function __construct(
		App $app,
	) {
		$this->factory = $app->factory();
		$this->renderer = $app->container()->tag(Renderer::class)->get('panel');
	}

	public function get(string $template): PanelRenderer
	{
		return new PanelRenderer($this->renderer, $this->factory, $template);
	}
}
