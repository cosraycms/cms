<?php

declare(strict_types=1);

namespace Cosray\Plugin;

use Celema\Router\Route;
use Cosray\PanelRenderers;
use Psr\Http\Server\MiddlewareInterface as Middleware;

/**
 * One route of a plugin's panel group.
 *
 * @api
 */
final class PanelRoute
{
	public function __construct(
		private readonly Route $route,
		private readonly PanelRenderers $renderers,
	) {}

	/**
	 * Renders the context the controller returns with a panel template, such
	 * as `acme-shop:orders`. Without it, the controller returns a response.
	 *
	 * @param non-empty-string $template
	 */
	public function render(string $template): self
	{
		$this->route->after($this->renderers->get($template));

		return $this;
	}

	public function middleware(Middleware ...$middleware): self
	{
		$this->route->middleware(...$middleware);

		return $this;
	}
}
