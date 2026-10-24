<?php

declare(strict_types=1);

namespace Cosray\Api;

use Celema\Core\Exception\HttpBadRequest;
use Cosray\Locales;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/** The page negotiation falls back to the default locale; an API client asked for a specific one. */
final class CheckLocale implements Middleware
{
	#[Override]
	public function process(Request $request, Handler $handler): Response
	{
		$locale = $request->getQueryParams()['locale'] ?? null;
		$locales = $request->getAttribute('locales');

		if ($locale !== null && $locales instanceof Locales && (!is_string($locale) || !$locales->exists($locale))) {
			throw new HttpBadRequest($request, payload: ['message' => 'Unknown locale.']);
		}

		return $handler->handle($request);
	}
}
