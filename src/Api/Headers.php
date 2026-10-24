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

			return $origin === null
				? $response
				: $response
					->withHeader('Access-Control-Allow-Origin', $origin)
					->withHeader('Access-Control-Allow-Methods', self::METHODS)
					->withHeader('Access-Control-Allow-Headers', self::ALLOWED)
					->withHeader('Access-Control-Max-Age', '86400')
					->withAddedHeader('Vary', 'Origin');
		}

		$response = $handler->handle($request);

		if ($origin !== null) {
			$response = $response
				->withHeader('Access-Control-Allow-Origin', $origin)
				->withHeader('Access-Control-Expose-Headers', self::EXPOSED)
				->withAddedHeader('Vary', 'Origin');
		}

		return $response
			->withHeader('Cache-Control', $this->cacheControl($request, $response))
			->withAddedHeader('Vary', 'Authorization');
	}

	private function cacheControl(Request $request, Response $response): string
	{
		$read = $request->getMethod() === 'GET' && $response->getStatusCode() < 300;

		if (!$read || $request->hasHeader('Authorization')) {
			return 'private, no-store';
		}

		return $this->maxAge > 0 ? "public, max-age={$this->maxAge}" : 'no-cache';
	}

	private function allowedOrigin(string $origin): ?string
	{
		if ($origin === '') {
			return null;
		}

		if (in_array('*', $this->origins, true)) {
			return '*';
		}

		return in_array($origin, $this->origins, true) ? $origin : null;
	}
}
