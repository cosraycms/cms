<?php

declare(strict_types=1);

namespace Cosray\Finder\Output;

use Cosray\Context;
use Cosray\Exception\ParserOutputException;
use Cosray\Finder\Input\Token;
use Cosray\Finder\Input\TokenType;

final readonly class UrlPath extends Expression implements Output
{
	public function __construct(
		public Token $left,
		public Token $operator,
		public Token $right,
		private Context $context,
	) {}

	public function get(): string
	{
		[$pathToken, $valueToken, $operator] = $this->normalize();
		[$localeClause, $isNegated, $condition] = $this->condition($valueToken, $operator);

		// Safe SQL fragment: table() validates the hardcoded identifier and configured prefix.
		$table = $this->context->config->db->table('url_paths', $this->context->db->getPdoDriver());

		return sprintf(
			'%sEXISTS (SELECT 1 FROM %s p WHERE p.node = n.node AND p.inactive IS NULL%s AND %s)',
			$isNegated ? 'NOT ' : '',
			$table,
			$localeClause,
			$condition,
		);
	}

	/**
	 * @return array{0: Token, 1: Token, 2: TokenType}
	 */
	private function normalize(): array
	{
		if ($this->left->type === TokenType::Path) {
			return [$this->left, $this->right, $this->operator->type];
		}

		if ($this->right->type !== TokenType::Path) {
			throw new ParserOutputException($this->left, 'A path expression requires a path operand.');
		}

		return [$this->right, $this->left, $this->reverse($this->operator->type)];
	}

	private function reverse(TokenType $type): TokenType
	{
		return match ($type) {
			TokenType::Greater => TokenType::Less,
			TokenType::GreaterEqual => TokenType::LessEqual,
			TokenType::Less => TokenType::Greater,
			TokenType::LessEqual => TokenType::GreaterEqual,
			default => $type,
		};
	}

	/**
	 * @return array{0: string, 1: bool, 2: string}
	 */
	private function condition(#[\SensitiveParameter] Token $valueToken, TokenType $operator): array
	{
		$localeClause = $this->localeClause();

		return match ($operator) {
			TokenType::Equal => [$localeClause, false, $this->isComparison($valueToken, true)],
			TokenType::Unequal => [$localeClause, true, $this->isComparison($valueToken, true)],
			TokenType::Like => [$localeClause, false, $this->scalarComparison($valueToken, 'LIKE')],
			TokenType::Unlike => [$localeClause, true, $this->scalarComparison($valueToken, 'LIKE')],
			TokenType::ILike => [$localeClause, false, $this->scalarComparison($valueToken, 'ILIKE')],
			TokenType::IUnlike => [$localeClause, true, $this->scalarComparison($valueToken, 'ILIKE')],
			TokenType::Regex => [$localeClause, false, $this->scalarComparison($valueToken, '~')],
			TokenType::NotRegex => [$localeClause, true, $this->scalarComparison($valueToken, '~')],
			TokenType::IRegex => [$localeClause, false, $this->scalarComparison($valueToken, '~*')],
			TokenType::INotRegex => [$localeClause, true, $this->scalarComparison($valueToken, '~*')],
			TokenType::In => [$localeClause, false, $this->scalarComparison($valueToken, 'IN')],
			TokenType::NotIn => [$localeClause, true, $this->scalarComparison($valueToken, 'IN')],
			TokenType::Greater => [$localeClause, false, $this->scalarComparison($valueToken, '>')],
			TokenType::GreaterEqual => [$localeClause, false, $this->scalarComparison($valueToken, '>=')],
			TokenType::Less => [$localeClause, false, $this->scalarComparison($valueToken, '<')],
			TokenType::LessEqual => [$localeClause, false, $this->scalarComparison($valueToken, '<=')],
			default => throw new ParserOutputException(
				$this->operator,
				'Operator not supported for path expressions.',
			),
		};
	}

	private function isComparison(#[\SensitiveParameter] Token $valueToken, bool $allowNull): string
	{
		if ($valueToken->type === TokenType::Null) {
			if (!$allowNull) {
				throw new ParserOutputException($valueToken, 'NULL is not supported for this path comparison.');
			}

			return 'p.path IS NULL';
		}

		return 'p.path = ' . $this->literal($valueToken);
	}

	private function scalarComparison(
		#[\SensitiveParameter]
		Token $valueToken,
		string $operator,
	): string {
		if ($valueToken->type === TokenType::Null) {
			throw new ParserOutputException(
				$valueToken,
				'NULL is only supported with = or != for path expressions.',
			);
		}

		return 'p.path ' . $operator . ' ' . $this->literal($valueToken);
	}

	private function literal(#[\SensitiveParameter] Token $token): string
	{
		return match ($token->type) {
			TokenType::String => $this->context->db->quote($token->lexeme),
			TokenType::Number, TokenType::Boolean => $this->context->db->quote($token->lexeme),
			TokenType::List => $token->lexeme,
			default => throw new ParserOutputException(
				$token,
				'Path comparisons only support literal values.',
			),
		};
	}

	private function localeClause(): string
	{
		$path = $this->left->type === TokenType::Path ? $this->left->lexeme : $this->right->lexeme;
		$parts = explode('.', $path);

		if (count($parts) === 1 || count($parts) === 2 && $parts[1] === '*') {
			return '';
		}

		if (count($parts) !== 2) {
			throw new ParserOutputException(
				$this->left,
				'Invalid path selector. Use path, path.?, path.*, or path.<locale>.',
			);
		}

		$selector = $parts[1] === '?' ? $this->context->localeId() : $parts[1];

		if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $selector) !== 1) {
			throw new ParserOutputException($this->left, 'Invalid locale in path selector.');
		}

		return ' AND p.locale = ' . $this->context->db->quote($selector);
	}
}
