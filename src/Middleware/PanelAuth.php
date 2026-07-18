<?php

declare(strict_types=1);

namespace Cosray\Middleware;

use Celema\Core\Factory\Factory;
use Cosray\Auth;
use Cosray\Config;
use Cosray\Users;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class PanelAuth implements Middleware
{
	public function __construct(
		private readonly Config $config,
		private readonly Users $users,
		private readonly Factory $factory,
	) {}

	public function process(Request $request, Handler $handler): Response
	{
		$session = $request->getAttribute('session', null);
		$auth = new Auth($request, $this->users, $this->config, $session);
		$user = $auth->user();

		if ($user !== null && $user->hasPermission('panel')) {
			return $handler->handle($request);
		}

		return $this->unauthorized($request, $user !== null);
	}

	private function unauthorized(Request $request, bool $authenticated): Response
	{
		$url = $this->loginUrl($request);

		if ($request->hasHeader('HX-Request')) {
			$status = $authenticated ? 403 : 401;

			return $this->factory
				->response($status)
				->withHeader('HX-Redirect', $url);
		}

		return $this->factory
			->response(303)
			->withHeader('Location', $url);
	}

	private function loginUrl(Request $request): string
	{
		$panelPath = $this->config->panel->path;
		$path = $request->getUri()->getPath();

		if ($path === '') {
			$path = '/';
		}

		$query = $request->getUri()->getQuery();
		$next = $query === '' ? $path : $path . '?' . $query;
		$params = http_build_query(['next' => $next]);

		return $panelPath . '/login?' . $params;
	}
}
