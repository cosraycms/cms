<?php

declare(strict_types=1);

namespace Cosray\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/** Names the plugin whose panel route handles the request, as the `plugin` attribute. */
final class PanelPlugin implements Middleware
{
	public function __construct(
		private readonly string $id,
	) {}

	#[Override]
	public function process(Request $request, Handler $handler): Response
	{
		return $handler->handle($request->withAttribute('plugin', $this->id));
	}
}
