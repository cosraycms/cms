<?php

declare(strict_types=1);

namespace Cosray\Exception;

use Celema\Core\Exception\HttpError;

final class AccessThrottled extends HttpError
{
	protected const int code = 429;
	protected const string message = 'Too many password attempts';
}
