<?php

declare(strict_types=1);

namespace Cosray\Exception;

use Celema\Core\Exception\HttpError;

// @mago-expect lint:constant-name HttpError reads its status and message from lowercase constants.
final class DatabaseUnavailable extends HttpError implements CmsException
{
	protected const int code = 503;
	protected const string message = 'Service Unavailable';
}
