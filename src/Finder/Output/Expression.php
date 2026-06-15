<?php

declare(strict_types=1);

namespace Cosray\Finder\Output;

use Celemas\Quma\Database;
use Cosray\Context;
use Cosray\Exception\ParserException;
use Cosray\Finder\CompilesField;
use Cosray\Finder\Input\Token;
use Cosray\Finder\Input\TokenType;

abstract readonly class Expression
{
	use CompilesField;

	protected function getOperator(TokenType $type): string
	{
		return match ($type) {
			TokenType::LeftParen => '(',
			TokenType::RightParen => ')',
			TokenType::Equal => '=',
			TokenType::Greater => '>',
			TokenType::GreaterEqual => '>=',
			TokenType::Less => '<',
			TokenType::LessEqual => '<=',
			TokenType::Like => 'LIKE',
			TokenType::ILike => 'ILIKE',
			TokenType::Unequal => '!=',
			TokenType::Unlike => 'NOT LIKE',
			TokenType::IUnlike => 'NOT ILIKE',
			TokenType::And => 'AND',
			TokenType::Or => 'OR',
			TokenType::In => 'IN',
			TokenType::NotIn => 'NOT IN',
			default => throw new ParserException('Invalid expression operator: ' . $type->name),
		};
	}

	protected function getOperand(
		#[\SensitiveParameter]
		Token $token,
		Database $db,
		array $builtins,
		?Context $context = null,
	): string {
		return match ($token->type) {
			TokenType::Boolean => strtolower($token->lexeme),
			TokenType::Field => $this->compileField(
				$token->lexeme,
				'n.content',
				localeIds: $this->localeIds($context),
			),
			TokenType::Builtin => $builtins[$token->lexeme],
			TokenType::Keyword => $this->translateKeyword($token->lexeme),
			TokenType::Null => 'NULL',
			TokenType::Number => $token->lexeme,
			TokenType::String => $db->quote($token->lexeme),
			TokenType::List => $token->lexeme,
		};
	}

	private function localeIds(?Context $context): array
	{
		if (!$context) {
			return ['zxx'];
		}

		$ids = [];
		$locale = $context->locale();

		while ($locale) {
			$ids[] = $locale->id;
			$locale = $locale->fallback();
		}

		return $ids;
	}

	protected function translateKeyword(string $keyword): string
	{
		return match ($keyword) { 'now' => 'NOW()' };
	}
}
