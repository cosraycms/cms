<?php

declare(strict_types=1);

namespace Cosray\Api\Exception;

use Celema\Core\Exception\HttpError;

// @mago-expect lint:constant-name HttpError reads its status and message from lowercase constants.
final class UnsupportedMediaType extends HttpError
{
	protected const int code = 415;
	protected const string message = 'Unsupported Media Type';
}
