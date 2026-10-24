<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Cosray\Context;
use Cosray\Exception\ParserException;
use Cosray\Finder\Input\Token;
use Cosray\Finder\Input\TokenGroup;
use Cosray\Finder\Input\TokenType;
use Cosray\Finder\Output\Comparison;
use Cosray\Finder\Output\Exists;
use Cosray\Finder\Output\NodeReference;
use Cosray\Finder\Output\NullComparison;
use Cosray\Finder\Output\UrlPath;

/**
 * Compiles a structured filter into the same conditions the query language
 * produces, without ever building a query string, so values need no quoting
 * by the caller:
 *
 *     ['or' => [
 *         ['type' => 'global-topic', 'tenant' => 'T1'],
 *         ['type' => 'local-topic', 'tenant' => 'T1', 'tutorial' => ['in' => ['a', 'b']]],
 *     ]]
 *
 * An object ANDs its members; `and`, `or` and `not` combine filters. A member
 * maps a field to a value, which means `eq`, or to operators: `eq`, `ne`, `lt`,
 * `lte`, `gt`, `gte`, `in`, `nin`, `like`, `ilike`, `exists` and `null`.
 * Content fields compare their effective value in the active locale; `title.de`
 * reads one locale. `path`, `path.<locale>`, `references` and
 * `references.<field>` work as in the query language.
 */
final class Filter
{
	public const int MAX_DEPTH = 8;

	private const string FIELD = '/^[A-Za-z][A-Za-z0-9_-]*(\.[A-Za-z0-9_-]+)*$/';

	private const array COMPARISONS = [
		'eq' => [TokenType::Equal, '='],
		'ne' => [TokenType::Unequal, '!='],
		'lt' => [TokenType::Less, '<'],
		'lte' => [TokenType::LessEqual, '<='],
		'gt' => [TokenType::Greater, '>'],
		'gte' => [TokenType::GreaterEqual, '>='],
		'in' => [TokenType::In, '@'],
		'nin' => [TokenType::NotIn, '!@'],
		'like' => [TokenType::Like, '~~'],
		'ilike' => [TokenType::ILike, '~~*'],
	];

	/**
	 * @param array<string, string> $builtins the builtin names and their SQL
	 * @param ?list<string> $allowed builtins a caller may use; null allows all
	 */
	public function __construct(
		private readonly Context $context,
		private readonly array $builtins,
		private readonly ?array $allowed = null,
	) {}

	/** @param array<array-key, mixed> $filter */
	public function compile(array $filter): string
	{
		return $this->filter($filter, 1);
	}

	/** @param array<array-key, mixed> $filter */
	private function filter(array $filter, int $depth): string
	{
		if ($depth > self::MAX_DEPTH) {
			throw new ParserException('The filter nests too deeply.');
		}

		if ($filter === [] || array_is_list($filter)) {
			throw new ParserException('A filter is an object of conditions.');
		}

		$conditions = [];

		foreach ($filter as $key => $value) {
			$key = (string) $key;
			$conditions[] = match ($key) {
				'and', 'or' => $this->combine($key, $value, $depth),
				'not' => 'NOT (' . $this->filter($this->object($value, 'not'), $depth + 1) . ')',
				default => $this->member($key, $value),
			};
		}

		return implode(' AND ', $conditions);
	}

	private function combine(string $operator, mixed $filters, int $depth): string
	{
		if (!is_array($filters) || $filters === [] || !array_is_list($filters)) {
			throw new ParserException("`{$operator}` takes a list of filters.");
		}

		$parts = array_map(
			fn(mixed $filter): string => (
				'('
				. $this->filter(
					$this->object($filter, $operator),
					$depth + 1,
				)
				. ')'
			),
			$filters,
		);

		return '(' . implode(' ' . strtoupper($operator) . ' ', $parts) . ')';
	}

	private function member(string $field, mixed $value): string
	{
		$left = $this->operand($field);

		if (!is_array($value) || array_is_list($value)) {
			return $this->condition($left, 'eq', $value);
		}

		$conditions = [];

		foreach ($value as $operator => $operand) {
			$conditions[] = $this->condition($left, (string) $operator, $operand);
		}

		return implode(' AND ', $conditions);
	}

	private function condition(Token $left, string $operator, mixed $value): string
	{
		if ($operator === 'exists') {
			return $this->exists($left, $value);
		}

		if ($operator === 'null') {
			if (!is_bool($value)) {
				throw new ParserException('`null` takes true or false.');
			}

			return $this->comparison($left, $value ? 'eq' : 'ne', null);
		}

		if (!array_key_exists($operator, self::COMPARISONS)) {
			throw new ParserException("Unknown filter operator `{$operator}`.");
		}

		if ($value === null && !in_array($operator, ['eq', 'ne'], true)) {
			throw new ParserException("`{$operator}` cannot compare with null.");
		}

		return $this->comparison($left, $operator, $value);
	}

	private function comparison(Token $left, string $operator, mixed $value): string
	{
		[$type, $lexeme] = self::COMPARISONS[$operator];
		$operatorToken = new Token(TokenGroup::Operator, $type, 0, $lexeme);
		$list = in_array($operator, ['in', 'nin'], true);
		$right = $list ? $this->list($value, $operator) : $this->literal($value);

		if ($right->type === TokenType::Null) {
			return new NullComparison($left, $operatorToken, $right, $this->context, $this->builtins)->get();
		}

		return match ($left->type) {
			TokenType::Path => new UrlPath($left, $operatorToken, $right, $this->context)->get(),
			TokenType::Reference => new NodeReference($left, $operatorToken, $right, $this->context)->get(),
			default => new Comparison($left, $operatorToken, $right, $this->context, $this->builtins)->get(),
		};
	}

	private function exists(Token $left, mixed $value): string
	{
		if (!is_bool($value)) {
			throw new ParserException('`exists` takes true or false.');
		}

		if ($left->type !== TokenType::Field) {
			throw new ParserException('`exists` applies to content fields only.');
		}

		$condition = new Exists($left, $this->context)->get();

		return $value ? $condition : "NOT ({$condition})";
	}

	private function operand(string $field): Token
	{
		if (preg_match(self::FIELD, $field) !== 1) {
			throw new ParserException("`{$field}` is not a field name.");
		}

		if ($field === 'path' || str_starts_with($field, 'path.')) {
			return new Token(TokenGroup::Operand, TokenType::Path, 0, $field);
		}

		if ($field === 'references' || str_starts_with($field, 'references.')) {
			return new Token(TokenGroup::Operand, TokenType::Reference, 0, $field);
		}

		if (array_key_exists($field, $this->builtins)) {
			if ($this->allowed !== null && !in_array($field, $this->allowed, true)) {
				throw new ParserException("`{$field}` cannot be filtered on.");
			}

			return new Token(TokenGroup::Operand, TokenType::Builtin, 0, $field);
		}

		return new Token(TokenGroup::Operand, TokenType::Field, 0, $field);
	}

	private function literal(mixed $value): Token
	{
		return match (true) {
			$value === null => new Token(TokenGroup::Operand, TokenType::Null, 0, 'null'),
			is_bool($value) => new Token(TokenGroup::Operand, TokenType::Boolean, 0, $value ? 'true' : 'false'),
			is_int($value) => new Token(TokenGroup::Operand, TokenType::Number, 0, (string) $value),
			is_float($value) && is_finite($value) => new Token(
				TokenGroup::Operand,
				TokenType::Number,
				0,
				json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
			),
			is_string($value) => new Token(TokenGroup::Operand, TokenType::String, 0, $value),
			default => throw new ParserException('A filter value is a string, number, boolean or null.'),
		};
	}

	private function list(mixed $values, string $operator): Token
	{
		if (!is_array($values) || $values === [] || !array_is_list($values)) {
			throw new ParserException("`{$operator}` takes a non-empty list.");
		}

		$items = array_map(function (mixed $value) use ($operator): Token {
			if (!is_string($value) && !is_int($value) && !(is_float($value) && is_finite($value))) {
				throw new ParserException("`{$operator}` lists hold strings or numbers.");
			}

			return $this->literal($value);
		}, $values);

		return Token::fromList(TokenGroup::Operand, TokenType::List, 0, $items, 0, $this->context->db);
	}

	/** @return array<array-key, mixed> */
	private function object(mixed $value, string $operator): array
	{
		if (!is_array($value)) {
			throw new ParserException("`{$operator}` takes filter objects.");
		}

		return $value;
	}
}
