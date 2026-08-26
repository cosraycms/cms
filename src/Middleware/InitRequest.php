<?php

declare(strict_types=1);

namespace Cosray\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class InitRequest implements Middleware
{
	public function process(Request $request, Handler $handler): Response
	{
		// See if it's a JSON request
		if (
			isset($_SERVER['HTTP_X_REQUESTED_WITH'])
			&& strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
		) {
			$request = $request->withAttribute('isXhr', true);
		} else {
			if ($request->hasHeader('Accept') && $request->getHeaderLine('Accept') === 'application/json') {
				$request = $request->withAttribute('isXhr', true);
			} else {
				$request = $request->withAttribute('isXhr', false);
			}
		}

		return $handler->handle($request);
	}
}
