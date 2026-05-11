<?php

declare(strict_types=1);

namespace Celemas\Cms\Boiler\Error;

use Celemas\Boiler\Engine;
use Celemas\Core\Exception\HttpError;
use Celemas\Error\Renderer as RendererInterface;
use Override;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * @psalm-import-type DirsInput from \Celemas\Boiler\Engine
 */
final class Renderer implements RendererInterface
{
	/**
	 * @param non-empty-string $template
	 * @param DirsInput $dirs
	 * @param array<string, mixed> $context
	 * @param list<class-string> $trusted
	 */
	public function __construct(
		private string $template,
		private string|array $dirs,
		private array $context = [],
		private array $trusted = [],
		private bool $autoescape = true,
	) {}

	#[Override]
	public function render(
		Throwable $exception,
		ResponseFactory $factory,
		?Request $request,
		bool $debug,
	): Response {
		$payload = null;

		if ($exception instanceof HttpError) {
			$payload = $exception->payload();
			$request = $exception->request() ?: $request;
		}
		$dirs = $this->dirs;
		$engine = $this->createEngine($dirs);

		$code = (int) $exception->getCode();
		$status = $code < 100 || $code > 599 ? 500 : $code;
		$response = $factory->createResponse($status);

		if ($request && $this->isJsonOnlyRequest($request)) {
			$response = $response->withHeader('Content-Type', 'application/json');
			$json = json_encode($payload);
			$response->getBody()->write($json === false ? '{}' : $json);
		} else {
			$response->getBody()->write($engine->render($this->template, array_merge($this->context, [
				'request' => $request,
				'exception' => $exception,
				'payload' => $payload,
			])));
		}

		return $response;
	}

	/** @param DirsInput $dirs */
	private function createEngine(string|array $dirs): Engine
	{
		return $this->autoescape
			? Engine::create($dirs, trusted: $this->trusted)
			: Engine::unescaped($dirs, trusted: $this->trusted);
	}

	private function isJsonOnlyRequest(Request $request): bool
	{
		// Split the Accept header into individual media types
		$acceptedTypes = array_map('trim', explode(',', $request->getHeaderLine('Accept')));

		foreach ($acceptedTypes as $type) {
			// Remove quality values if present
			$mediaType = trim(explode(';', $type)[0]);

			if ($mediaType !== 'application/json') {
				return false;
			}
		}

		return true;
	}
}
