<?php

declare(strict_types=1);

namespace Cosray\Finder\Output;

use Cosray\Finder\Input\Token;
use Override;

class RightParen implements Output
{
	public function __construct(
		#[\SensitiveParameter]
		public Token $token,
	) {}

	#[Override]
	public function get(): string
	{
		return ')';
	}
}
