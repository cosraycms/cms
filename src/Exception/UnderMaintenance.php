<?php

declare(strict_types=1);

namespace Cosray\Exception;

use Celema\Core\Exception\HttpError;

/**
 * A request maintenance mode refuses. Its payload carries `retry`, the
 * seconds for `Retry-After`, and the operator's `message`, if any.
 */
// @mago-expect lint:constant-name HttpError reads its status and message from lowercase constants.
final class UnderMaintenance extends HttpError implements CmsException
{
	protected const int code = 503;
	protected const string message = 'Service Unavailable';

	public function retry(): int
	{
		$payload = $this->payload();
		$retry = is_array($payload) ? $payload['retry'] ?? null : null;

		return is_int($retry) && $retry > 0 ? $retry : 60;
	}
}
