<?php

declare(strict_types=1);

namespace Celemas\Cms;

use Celemas\Core\Factory\Factory;
use Celemas\Core\Response;
use Celemas\Router\After;
use Override;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Traversable;

/** @psalm-api */
final class PanelRenderer implements AfterRenderer
{
	public function __construct(
		private Renderer $renderer,
		private Factory $factory,
		private string $template,
	) {}

	#[Override]
	public function handle(mixed $data): PsrResponse
	{
		if ($data instanceof PsrResponse) {
			return $data;
		}

		if ($data instanceof Response) {
			return $data->unwrap();
		}

		if ($data instanceof Traversable) {
			return $this->response(iterator_to_array($data));
		}

		return $this->response($data);
	}

	#[Override]
	public function replace(After $handler): bool
	{
		return is_a($handler, Renderer::class);
	}

	private function response(array $context): PsrResponse
	{
		$html = $this->renderer->render($this->template, $context);

		$response = $this->factory
			->response()
			->withHeader('Content-Type', 'text/html');
		$response->getBody()->write($html);

		return $response;
	}
}
