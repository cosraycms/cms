<?php

declare(strict_types=1);

namespace Cosray\Api;

use Celema\Core\Exception\HttpUnauthorized;
use Cosray\Users;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * The user a bearer token acts as, as the `user` request attribute; null
 * without a token. A token that does not authenticate is refused rather than
 * treated as anonymous, so a client learns that its token stopped working.
 */
final class Authenticate implements Middleware
{
	public function __construct(
		private readonly Users $users,
	) {}

	#[Override]
	public function process(Request $request, Handler $handler): Response
	{
		$header = trim($request->getHeaderLine('Authorization'));

		if ($header === '') {
			return $handler->handle($request->withAttribute('user', null));
		}

		if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches) !== 1) {
			throw new HttpUnauthorized($request, payload: [
				'message' => 'Send the token as `Authorization: Bearer <token>`.',
			]);
		}

		$user = $this->users->byAuthToken($matches[1])
			?? throw new HttpUnauthorized($request, payload: [
				'message' => 'The token is unknown, revoked or expired.',
			]);

		return $handler->handle($request->withAttribute('user', $user));
	}
}
