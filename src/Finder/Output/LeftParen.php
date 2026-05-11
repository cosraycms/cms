<?php

declare(strict_types=1);

namespace Celemas\Cms\Finder\Output;

use Celemas\Cms\Finder\Input\Token;

class LeftParen implements Output
{
	public function __construct(
		#[\SensitiveParameter]
		public Token $token,
	) {}

	public function get(): string
	{
		return '(';
	}
}
