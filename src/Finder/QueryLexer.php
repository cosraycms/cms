<?php

declare(strict_types=1);

namespace Celemas\Cms\Finder;

use Celemas\Cms\Exception\ParserException;
use Celemas\Cms\Finder\Input\Token;
use Celemas\Cms\Finder\Input\TokenGroup;
use Celemas\Cms\Finder\Input\TokenType;

final class QueryLexer
{
	private int $start = 0;
	private int $current = 0;

	/** @var list<Token> */
	private array $tokens = [];

	private readonly string $query;
	private readonly array $source;
	private readonly int $length;

	public function __construct(
		private readonly array $builtins = [],
	) {}

	public function tokens(string $query): array
	{
		$this->query = $query;
		$this->source = mb_str_split($query);
		$this->length = count($this->source);

		while (!$this->atEnd()) {
			$this->start = $this->current;
			$this->scan();
		}

		return $this->tokens;
	}

	private function scan(): void
	{
		$char = $this->advance();

		switch ($char) {
			case ' ':
			case ',': // comma is ignored that it can be used inside of lists
			case "\t":
			case "\n":
			case "\r":
				break;
			case '(':
				$this->addToken(TokenGroup::LeftParen, TokenType::LeftParen);
				break;
			case ')':
				$this->addToken(TokenGroup::RightParen, TokenType::RightParen);
				break;
			case '[':
				$this->addToken(TokenGroup::LeftBracket, TokenType::LeftBracket);
				break;
			case ']':
				$this->addToken(TokenGroup::RightBracket, TokenType::RightBracket);
				break;
			case '&':
				$this->addBooleanOperator(TokenType::And);
				break;
			case '|':
				$this->addBooleanOperator(TokenType::Or);
				break;
			case '=':
				$this->addOperator(TokenType::Equal);
				break;
			case '@':
				$this->addOperator(TokenType::In);
				break;
			case '~':
				if ($this->matchNext('~')) {
					if ($this->matchNext('*')) {
						// ~~*
						$this->addOperator(TokenType::ILike);
					} else {
						// ~~
						$this->addOperator(TokenType::Like);
					}
				} elseif ($this->matchNext('*')) {
					// ~*
					$this->addOperator(TokenType::IRegex);
				} else {
					// ~
					$this->addOperator(TokenType::Regex);
				}
				break;
			case '!':
				if ($this->matchNext('=')) {
					// !=
					$this->addOperator(TokenType::Unequal);
				} elseif ($this->matchNext('@')) {
					$this->addOperator(TokenType::NotIn);
				} elseif ($this->matchNext('~')) {
					if ($this->matchNext('~')) {
						if ($this->matchNext('*')) {
							// !~~*
							$this->addOperator(TokenType::IUnlike);
						} else {
							// !~~
							$this->addOperator(TokenType::Unlike);
						}
					} elseif ($this->matchNext('*')) {
						// !~*
						$this->addOperator(TokenType::INotRegex);
					} else {
						// !~
						$this->addOperator(TokenType::NotRegex);
					}
				} else {
					$this->error(
						"Invalid operator '!'. "
						. "It can only be used in combination with '=' "
						. "and '~', i. e. '!=' and '!~'",
					);
				}
				break;
			case '>':
				if ($this->matchNext('=')) {
					$this->addOperator(TokenType::GreaterEqual);
				} else {
					$this->addOperator(TokenType::Greater);
				}
				break;
			case '<':
				if ($this->matchNext('=')) {
					$this->addOperator(TokenType::LessEqual);
				} else {
					$this->addOperator(TokenType::Less);
				}
				break;
			case '"':
			case "'":
			case '/':
				$this->consumeString($char);
				break;
			default:
				if (is_numeric($char) || $char === '-') {
					$this->consumeNumber($char);
				} elseif ($this->isIdentifier($char)) {
					$this->consumeIdentifier();
				} else {
					$this->error("Syntax error, unknown character '{$char}'");
				}
		}
	}

	private function addOperator(TokenType $type): void
	{
		$this->addToken(TokenGroup::Operator, $type);
	}

	private function addBooleanOperator(TokenType $type): void
	{
		$this->addToken(TokenGroup::BooleanOperator, $type);
	}

	private function isIdentifier(string $char): bool
	{
		return ctype_alpha($char);
	}

	private function consumeIdentifier(): void
	{
		$wasDot = false;
		$wasSpecial = false;

		while (true) {
			$char = $this->peek();
			$isDot = $char === '.';
			$isSpecial = $char === '*' || $char === '?';

			$valid =
				ctype_alpha($char)
				|| ctype_digit($char)
				|| $char === '_'
				|| $char === '-'
				|| $isDot
				|| $wasDot && $isSpecial;

			if ($valid && $wasSpecial && !$isDot) {
				$this->error('Invalid use of special character (like ? or *) in identifier.');
			}

			$wasDot = $isDot;
			$wasSpecial = $isSpecial;

			if ($valid && !$this->atEnd()) {
				$this->advance();
			} else {
				$lexeme = $this->getLexeme();
				$type = $this->getIdentifierType($lexeme);
				$this->tokens[] = new Token(TokenGroup::Operand, $type, $this->start, $lexeme);

				break;
			}
		}
	}

	private function consumeNumber(string $char): void
	{
		if ($char === '-') {
			if (!is_numeric($this->peek())) {
				$this->error("Syntax error, unknown character '-'");
			}
		}

		while (is_numeric($this->peek())) {
			$this->advance();
		}

		if ($this->peek() === '.') {
			$this->advance();
			$hasFraction = false;

			while (is_numeric($this->peek())) {
				$hasFraction = true;
				$this->advance();
			}

			if (!$hasFraction) {
				$this->error('Invalid number.');
			}
		}

		$this->addToken(TokenGroup::Operand, TokenType::Number);
	}

	private function consumeString(string $char): void
	{
		while ($this->peek() !== $char && !$this->atEnd()) {
			if ($this->peek() === '\\' && $this->peekNext() === $char) {
				$this->advance();
			}

			$this->advance();
		}

		if ($this->atEnd()) {
			$this->error('Unterminated string.');
		}

		// Hop to the closing "
		$this->advance();

		if ($this->start === $this->current) {
			$lexeme = '';
		} else {
			$start = $this->start + 1;
			$length = $this->current - $this->start - 2;
			$slice = array_slice($this->source, $start, $length);
			$lexeme = implode('', $slice);
		}

		// $this->afterValue = true;
		$this->tokens[] = new Token(
			TokenGroup::Operand,
			TokenType::String,
			$this->start,
			str_replace('\\' . $char, $char, $lexeme),
		);
	}

	private function advance(): string
	{
		$result = $this->source[$this->current];
		$this->current++;

		return $result;
	}

	private function peek(): string
	{
		if ($this->atEnd()) {
			return '';
		}

		return $this->source[$this->current];
	}

	private function peekNext(): string
	{
		if (($this->current + 1) > ($this->length - 1)) {
			return '';
		}

		return $this->source[$this->current + 1];
	}

	private function matchNext(string $expected): bool
	{
		if ($this->atEnd()) {
			return false;
		}

		if ($this->source[$this->current] === $expected) {
			$this->current++;

			return true;
		}

		return false;
	}

	private function atEnd(): bool
	{
		return $this->current > ($this->length - 1);
	}

	private function addToken(TokenGroup $group, TokenType $type): void
	{
		$lexeme = $this->getLexeme();
		$this->tokens[] = new Token($group, $type, $this->start, $lexeme);
	}

	private function getLexeme(): string
	{
		$length = $this->current - $this->start;
		$slice = array_slice($this->source, $this->start, $length);

		return implode('', $slice);
	}

	private function getIdentifierType(string $lexeme): TokenType
	{
		if (str_ends_with($lexeme, '.') || str_contains($lexeme, '..')) {
			$this->error('Invalid use of dot (.) in indentifier');
		}

		switch ($lexeme) {
			case 'true':
			case 'false':
				return TokenType::Boolean;
			case 'null':
				return TokenType::Null;
			case 'now':
				return TokenType::Keyword;
			default:
				if ($lexeme === 'path' || str_starts_with($lexeme, 'path.')) {
					return TokenType::Path;
				}

				if (in_array($lexeme, $this->builtins, true)) {
					return TokenType::Builtin;
				}

				return TokenType::Field;
		}
	}

	/**
	 * @throws ParserException
	 */
	private function error(string $msg): never
	{
		throw new ParserException(
			"Parse error at position {$this->start}. {$msg}\n\n"
			. "Query: `{$this->query}`\n"
			. str_repeat(' ', $this->start + 8)
			. str_repeat('^', $this->current - $this->start)
			. "\n\n",
		);
	}
}
