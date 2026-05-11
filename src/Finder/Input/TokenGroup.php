<?php

declare(strict_types=1);

namespace Celemas\Cms\Finder\Input;

enum TokenGroup
{
	// Parenthesis used to group boolean expressions: ( )
	case LeftParen;
	case RightParen;

	// Brackets used for lists: [ ]
	case LeftBracket;
	case RightBracket;

	// Operators used in boolean expresssions: & |
	case BooleanOperator;

	// Fields and values
	case Operand;

	// Compare operators: = != !~ < > =< => ...
	case Operator;
}
