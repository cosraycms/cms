<?php

declare(strict_types=1);

namespace Celemas\Cms\Finder\Output;

use Celemas\Cms\Exception\ParserException;
use Celemas\Cms\Finder\Input\Token;
use Celemas\Cms\Finder\Input\TokenType;

class Operator implements Output
{
	public function __construct(
		#[\SensitiveParameter]
		public Token $token,
	) {}

	public function get(): string
	{
		return match ($this->token->type) {
			TokenType::And => ' AND ',
			TokenType::Or => ' OR ',
			default => throw new ParserException('Invalid boolean operator'),
		};
	}
}
