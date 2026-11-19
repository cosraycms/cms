<?php

declare(strict_types=1);

namespace Cosray\View\Boiler\Error;

use Celema\Core\Error\Handler;
use Celema\Quma\Exception\ConnectionFailed;
use Cosray\Exception\DatabaseUnavailable;
use Cosray\Exception\UnderMaintenance;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * Answers a database that cannot be reached with 503 instead of 500, and
 * tells clients when to come back from that and from maintenance.
 */
final class ErrorHandler extends Handler
{
	private const int RETRY_AFTER = 30;

	#[Override]
	public function response(Throwable $exception, Request $request): Response
	{
		$response = parent::response($exception, $request);

		return match (true) {
			$exception instanceof ConnectionFailed => $response->withHeader('Retry-After', (string) self::RETRY_AFTER),
			$exception instanceof UnderMaintenance => $response->withHeader(
				'Retry-After',
				(string) $exception->retry(),
			),
			default => $response,
		};
	}

	#[Override]
	protected function normalize(Throwable $exception, Request $request): Throwable
	{
		if ($exception instanceof ConnectionFailed) {
			return new DatabaseUnavailable($request, previous: $exception);
		}

		return parent::normalize($exception, $request);
	}
}
