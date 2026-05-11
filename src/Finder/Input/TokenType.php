<?php

declare(strict_types=1);

namespace Celemas\Cms\Finder\Input;

enum TokenType
{
	// Single character tokens
	case LeftParen;
	case RightParen;
	case LeftBracket;
	case RightBracket;

	// Operators
	case Equal;
	case Greater;
	case GreaterEqual;
	case ILike;
	case INotRegex;
	case IRegex;
	case IUnlike;
	case Less;
	case LessEqual;
	case Like;
	case NotRegex;
	case Regex;
	case Unequal;
	case Unlike;
	case In;
	case NotIn;

	// Boolean Operators
	case And;
	case Or;

	// Operands
	case Boolean;
	case Builtin;
	case Path;
	case Field;
	case Keyword;
	case Null;
	case Number;
	case String;
	case List;
}
