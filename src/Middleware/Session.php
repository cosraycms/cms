<?php

declare(strict_types=1);

namespace Celemas\Cms\Middleware;

use Celemas\Cms\Config;
use Celemas\Cms\Users;
use Celemas\Quma\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class Session implements Middleware
{
	public function __construct(
		protected Config $config,
		protected Database $db,
	) {}

	public function process(Request $request, Handler $handler): Response
	{
		$config = $this->config->session;
		$session = new \Celemas\Cms\Session(
			$config->options,
			$this->config->app->name,
			$config->handler,
		);

		$session->start();
		$expires = $config->options['gc_maxlifetime'];
		$lastActivity = $session->lastActivity();

		if ($lastActivity && (time() - $lastActivity) > $expires) {
			$session->destroy();
			$session->start();
		}

		$session->signalActivity();
		$userId = $session->authenticatedUserId();

		if ($userId) {
			$user = new Users($this->db)->byId($userId);
			$request = $request->withAttribute('user', $user);
		}

		$request = $request->withAttribute('session', $session);

		return $handler->handle($request);
	}
}
