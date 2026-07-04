<?php

declare(strict_types=1);

namespace Cosray;

use Celemas\Core\Factory\Factory;
use Celemas\Core\Response;
use Celemas\Router\After;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Traversable;

/** @psalm-api */
class JsonRenderer implements AfterRenderer
{
	public function __construct(
		protected Factory $factory,
	) {}

	public function handle(mixed $data): PsrResponse
	{
		if ($data instanceof PsrResponse) {
			return $data;
		}

		if ($data instanceof Response) {
			return $data->unwrap();
		}

		$flags = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

		if ($data instanceof Traversable) {
			return $this->response(json_encode(iterator_to_array($data), $flags));
		}

		return $this->response(json_encode($data, $flags));
	}

	public function response(string $json): PsrResponse
	{
		$response = $this->factory
			->response()
			->withHeader('Content-Type', 'application/json');
		$response->getBody()->write($json);

		return $response;
	}

	public function replace(After $handler): bool
	{
		return is_a($handler, Renderer::class);
	}
}
