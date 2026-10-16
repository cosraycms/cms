<?php

declare(strict_types=1);

namespace Cosray\Finder\Output;

use Cosray\Exception\ParserException;
use Cosray\Finder\Input\Token;
use Cosray\Finder\Input\TokenType;
use Override;

class Operator implements Output
{
	public function __construct(
		#[\SensitiveParameter]
		public Token $token,
	) {}

	#[Override]
	public function get(): string
	{
		return match ($this->token->type) {
			TokenType::And => ' AND ',
			TokenType::Or => ' OR ',
			default => throw new ParserException('Invalid boolean operator'),
		};
	}
}
