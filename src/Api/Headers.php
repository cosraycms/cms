<?php

declare(strict_types=1);

namespace Cosray\Api;

use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * CORS for the configured origins and caching rules: anonymous reads may be
 * kept by shared caches for `api.max_age` seconds, anything sent with a
 * token never is. The API reads no cookies, so no credentials are allowed.
 */
final class Headers implements Middleware
{
	private const string METHODS = 'GET, POST, PUT, PATCH, DELETE';
	private const string ALLOWED = 'Authorization, Content-Type, If-Match, If-None-Match';
	private const string EXPOSED = 'ETag, Location, Retry-After';

	/** @param list<string> $origins */
	public function __construct(
		private readonly Responses $responses,
		private readonly array $origins,
		private readonly int $maxAge,
	) {}

	#[Override]
	public function process(Request $request, Handler $handler): Response
	{
		$origin = $this->allowedOrigin($request->getHeaderLine('Origin'));

		if ($request->getMethod() === 'OPTIONS' && $request->hasHeader('Access-Control-Request-Method')) {
			$response = $this->responses->empty();

			return $this->vary(
				$origin === null
					? $response
					: $response
						->withHeader('Access-Control-Allow-Origin', $origin)
						->withHeader('Access-Control-Allow-Methods', self::METHODS)
						->withHeader('Access-Control-Allow-Headers', self::ALLOWED)
						->withHeader('Access-Control-Max-Age', '86400'),
			);
		}

		$response = $handler->handle($request);

		if ($origin !== null) {
			$response = $response
				->withHeader('Access-Control-Allow-Origin', $origin)
				->withHeader('Access-Control-Expose-Headers', self::EXPOSED);
		}

		return $this
			->vary($response)
			->withHeader('Cache-Control', $this->cacheControl($request, $response))
			->withAddedHeader('Vary', 'Authorization');
	}

	/**
	 * With listed origins the CORS headers depend on the request's Origin,
	 * also when it sends none, so a shared cache has to keep the answers apart.
	 */
	private function vary(Response $response): Response
	{
		return $this->origins === [] || in_array('*', $this->origins, true)
			? $response
			: $response->withAddedHeader('Vary', 'Origin');
	}

	private function cacheControl(Request $request, Response $response): string
	{
		$read = $request->getMethod() === 'GET' && $response->getStatusCode() < 300;

		if (!$read || $request->hasHeader('Authorization') || isset($request->getQueryParams()['preview'])) {
			return 'private, no-store';
		}

		return $this->maxAge > 0 ? "public, max-age={$this->maxAge}" : 'no-cache';
	}

	/** Any origin gets `*`, even a request without one, so every answer is the same. */
	private function allowedOrigin(string $origin): ?string
	{
		if (in_array('*', $this->origins, true)) {
			return '*';
		}

		return $origin !== '' && in_array($origin, $this->origins, true) ? $origin : null;
	}
}
