<?php

declare(strict_types=1);

namespace Celemas\Cms\Exception;

use Celemas\Cms\Finder\Input\Token;
use Throwable;

class ParserOutputException extends ParserException implements CmsException
{
	public function __construct(
		#[\SensitiveParameter]
		public readonly Token $token,
		string $message,
		int $code = 0,
		?Throwable $previous = null,
	) {
		parent::__construct($message, $code, $previous);
	}
}
