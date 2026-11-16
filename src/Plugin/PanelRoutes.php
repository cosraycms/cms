<?php

declare(strict_types=1);

namespace Cosray\Plugin;

use Celema\Router\Group;
use Celema\Router\Route;
use Cosray\PanelRenderers;

/**
 * A plugin's routes inside the panel, mounted under `{panel.path}/{id}` and
 * named `cms.panel.{id}.{name}`. They run behind the panel's session,
 * locale, CSRF check and sign-in; a controller enforces its permissions
 * with `#[Permission]`.
 *
 * @api
 */
final class PanelRoutes
{
	public function __construct(
		private readonly Group $group,
		private readonly PanelRenderers $renderers,
	) {}

	/** @param array{string, string}|callable|non-empty-string $endpoint e.g. [Controller::class, 'method'] */
	public function get(string $pattern, callable|array|string $endpoint, string $name): PanelRoute
	{
		return $this->route($this->group->get($pattern, $endpoint, $name));
	}

	/** @param array{string, string}|callable|non-empty-string $endpoint e.g. [Controller::class, 'method'] */
	public function post(string $pattern, callable|array|string $endpoint, string $name): PanelRoute
	{
		return $this->route($this->group->post($pattern, $endpoint, $name));
	}

	/** @param array{string, string}|callable|non-empty-string $endpoint e.g. [Controller::class, 'method'] */
	public function put(string $pattern, callable|array|string $endpoint, string $name): PanelRoute
	{
		return $this->route($this->group->put($pattern, $endpoint, $name));
	}

	/** @param array{string, string}|callable|non-empty-string $endpoint e.g. [Controller::class, 'method'] */
	public function patch(string $pattern, callable|array|string $endpoint, string $name): PanelRoute
	{
		return $this->route($this->group->patch($pattern, $endpoint, $name));
	}

	/** @param array{string, string}|callable|non-empty-string $endpoint e.g. [Controller::class, 'method'] */
	public function delete(string $pattern, callable|array|string $endpoint, string $name): PanelRoute
	{
		return $this->route($this->group->delete($pattern, $endpoint, $name));
	}

	private function route(Route $route): PanelRoute
	{
		return new PanelRoute($route, $this->renderers);
	}
}
