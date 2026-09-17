<?php

declare(strict_types=1);

namespace Cosray\Middleware;

use Attribute;
use Celema\Core\Exception\HttpForbidden;
use Celema\Core\Exception\HttpUnauthorized;
use Celema\Wire\Call;
use Cosray\Auth;
use Cosray\Config;
use Cosray\Security\Policy;
use Cosray\Users;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

#[Attribute, Call('init')]
class Permission implements Middleware
{
	protected Users $users;
	protected Config $config;
	protected Policy $policy;

	public function __construct(
		public readonly string $permission,
	) {}

	public function process(Request $request, Handler $handler): Response
	{
		$session = $request->getAttribute('session', null);

		$auth = new Auth(
			$request,
			$this->users,
			$this->config,
			$session,
		);
		$user = $auth->user();

		if ($user) {
			if (!$this->policy->permits($user, $this->permission)) {
				throw new HttpForbidden($request);
			}

			return $handler->handle($request);
		}

		throw new HttpUnauthorized($request);
	}

	public function init(Users $users, Config $config, Policy $policy): void
	{
		$this->users = $users;
		$this->config = $config;
		$this->policy = $policy;
	}
}
