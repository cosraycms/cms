<?php

declare(strict_types=1);

namespace Cosray\Api;

use Celema\Core\Exception\HttpError;
use Celema\Core\Exception\HttpUnauthorized;
use Celema\Quma\Exception\ConnectionFailed;
use Cosray\Exception\DatabaseUnavailable;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Log\LoggerInterface as Logger;
use Throwable;

/**
 * Turns every failure inside the API into problem details, so a site's own
 * error renderer never changes what API clients receive.
 */
final class Problems implements Middleware
{
	private const int RETRY_AFTER = 30;

	public function __construct(
		private readonly Responses $responses,
		private readonly Logger $logger,
		private readonly bool $debug = false,
	) {}

	#[Override]
	public function process(Request $request, Handler $handler): Response
	{
		try {
			return $handler->handle($request);
		} catch (ConnectionFailed|DatabaseUnavailable $e) {
			$this->logger->error('API request failed: the database is unavailable', ['exception' => $e]);

			return $this->responses->problem(503, headers: ['Retry-After' => (string) self::RETRY_AFTER]);
		} catch (HttpError $e) {
			return $this->httpError($e);
		} catch (Throwable $e) {
			$this->logger->error('API request failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

			return $this->responses->problem(500, $this->debug ? $e->getMessage() : null);
		}
	}

	private function httpError(HttpError $e): Response
	{
		$payload = $e->payload();
		$detail = is_array($payload) && is_string($payload['message'] ?? null) ? $payload['message'] : null;
		$extensions = is_array($payload) && is_array($payload['errors'] ?? null)
			? ['errors' => $payload['errors']]
			: [];
		$headers = $e instanceof HttpUnauthorized ? ['WWW-Authenticate' => 'Bearer realm="api"'] : [];
		$status = $e->statusCode();

		// Store reports invalid content as a bad request with the issues attached.
		if ($status === 400 && $extensions !== []) {
			$status = 422;
		}

		return $this->responses->problem($status, $detail, $extensions, $headers);
	}
}
