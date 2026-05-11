<?php

declare(strict_types=1);

namespace Celemas\Cms\Finder\Output;

use Celemas\Cms\Context;
use Celemas\Cms\Exception\ParserOutputException;
use Celemas\Cms\Finder\Input\Token;
use Celemas\Cms\Finder\Input\TokenType;

final readonly class Comparison extends Expression implements Output
{
	public function __construct(
		private Token $left,
		private Token $operator,
		private Token $right,
		private Context $context,
		private array $builtins,
	) {}

	public function get(): string
	{
		switch ($this->operator->type) {
			case TokenType::Like:
			case TokenType::Unlike:
			case TokenType::ILike:
			case TokenType::IUnlike:
			case TokenType::In:
			case TokenType::NotIn:
				return $this->getSqlExpression();
		}

		if ($this->left->type === TokenType::Field) {
			if ($this->right->type === TokenType::Builtin || $this->right->type === TokenType::Field) {
				return $this->getSqlExpression();
			}

			return $this->getJsonPathExpression();
		}

		if ($this->left->type === TokenType::Builtin) {
			return $this->getSqlExpression();
		}

		throw new ParserOutputException(
			$this->left,
			'Only fields or `path` are allowed on the left side of an expression.',
		);
	}

	private function getJsonPathExpression(): string
	{
		[$operator, $jsonOperator, $right, $negate] = match ($this->operator->type) {
			TokenType::Equal => ['@@', '==', $this->getRight(), false],
			TokenType::Regex => ['@?', '?', $this->getRegex(false), false],
			TokenType::IRegex => ['@?', '?', $this->getRegex(true), false],
			TokenType::NotRegex => ['@?', '?', $this->getRegex(false), true],
			TokenType::INotRegex => ['@?', '?', $this->getRegex(true), true],
			TokenType::In => ['@@', 'in', $this->getRight(), false],
			TokenType::NotIn => ['@@', 'nin', $this->getRight(), false],
			default => ['@@', $this->operator->lexeme, $this->getRight(), false],
		};

		$left = $this->getField();

		return sprintf(
			"%sn.content %s '$.%s %s %s'",
			$negate ? 'NOT ' : '',
			$operator,
			$left,
			$jsonOperator,
			$right,
		);
	}

	private function getRegex(bool $ignoreCase): string
	{
		if (!($this->right->type === TokenType::String)) {
			throw new ParserOutputException(
				$this->right,
				'Only strings are allowed on the right side of a regex expressions.',
			);
		}

		$case = $ignoreCase ? ' flag "i"' : '';

		// TODO: quote double quotes, check also in tests
		$pattern = '"' . trim($this->context->db->quote($this->right->lexeme), "'") . '"';

		return sprintf('(@ like_regex %s%s)', $pattern, $case);
	}

	private function getField(): string
	{
		$parts = explode('.', $this->left->lexeme);

		return match (count($parts)) {
			2 => $this->compileField($parts),
			1 => $parts[0] . '.value',
			default => $this->compileAccessor($parts),
		};
	}

	private function compileField(array $segments): string
	{
		return match ($segments[1]) {
			'*' => $segments[0] . '.value.*',
			'?' => $segments[0] . '.value.' . $this->getCurrentLocale(),
			default => implode('.', $segments),
		};
	}

	private function compileAccessor(array $segments): string
	{
		$accessor = implode('.', $segments);

		if (str_contains($accessor, '?')) {
			throw new ParserOutputException(
				$this->left,
				'The questionmark is allowed after the first dot only.',
			);
		}

		return $accessor;
	}

	private function getCurrentLocale(): string
	{
		return $this->context->localeId();
	}

	private function getRight(): string
	{
		return match ($this->right->type) {
			TokenType::String => $this->quote($this->right->lexeme),
			TokenType::Number, TokenType::Boolean, TokenType::List, TokenType::Null => $this->right->lexeme,
			default => throw new ParserOutputException(
				$this->right,
				'The right hand side in a field expression must be a literal',
			),
		};
	}

	private function getSqlExpression(): string
	{
		return sprintf(
			'%s %s %s',
			$this->getOperand($this->left, $this->context->db, $this->builtins),
			$this->getOperator($this->operator->type),
			$this->getOperand($this->right, $this->context->db, $this->builtins),
		);
	}

	private function quote(string $string): string
	{
		return sprintf(
			'"%s"',
			// Escape all unescaped double quotes
			// TODO: can prepended backslashes be exploited
			preg_replace(
				'/(?<!\\\\)(")/',
				'\\"',
				trim($this->context->db->quote($string), "'"),
			),
		);
	}
}
