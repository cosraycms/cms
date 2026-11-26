<?php

declare(strict_types=1);

namespace Cosray\Middleware;

use Celema\Core\Factory\Factory;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Keeps a site out of search engines while `app.indexable` is false: its
 * robots.txt turns every crawler away, a file of that name included, and
 * every response asks not to be indexed. Files the web server serves itself
 * never reach it; they fall under the robots.txt rule alone.
 */
final readonly class NoIndex implements Middleware
{
	public const string HEADER = 'noindex, nofollow';

	public function __construct(
		private Factory $factory,
		private string $urlPrefix = '',
	) {}

	#[Override]
	public function process(Request $request, Handler $handler): Response
	{
		if ($request->getUri()->getPath() === $this->urlPrefix . '/robots.txt') {
			return $this->factory
				->response()
				->withHeader('Content-Type', 'text/plain; charset=utf-8')
				->withHeader('X-Robots-Tag', self::HEADER)
				->withBody($this->factory->stream("User-agent: *\nDisallow: /\n"));
		}

		return $handler->handle($request)->withHeader('X-Robots-Tag', self::HEADER);
	}
}
