<?php

declare(strict_types=1);

namespace Cosray\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Only the panel itself and the origins in `panel.embed` may frame panel
 * pages. Every panel response says so, because an embedded editor frames
 * further panel pages and the policy applies to each of their ancestors.
 */
final class PanelFraming implements Middleware
{
	/** @param list<string> $origins */
	public function __construct(
		private readonly array $origins,
	) {}

	#[Override]
	public function process(Request $request, Handler $handler): Response
	{
		return $handler
			->handle($request)
			->withHeader('Content-Security-Policy', 'frame-ancestors ' . implode(' ', ["'self'", ...$this->origins]));
	}
}
