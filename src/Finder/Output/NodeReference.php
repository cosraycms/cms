<?php

declare(strict_types=1);

namespace Cosray\Finder\Output;

use Cosray\Context;
use Cosray\Exception\ParserOutputException;
use Cosray\Field\Field;
use Cosray\Finder\Input\Token;
use Cosray\Finder\Input\TokenType;

/**
 * Filters nodes by the nodes they point at.
 *
 * `references = '<uid>'` asks whether the node references that target
 * anywhere in its content and reads the `node_references` index, so it also
 * covers richtext links. `references.<field> = '<uid>'` narrows the question
 * to one `Reference` field and compiles to a jsonb containment test, which
 * stays on the content GIN index.
 */
final readonly class NodeReference extends Expression implements Output
{
	/** Only nodes carry content; menu items are indexed as their own owner kind. */
	private const string OWNER_TYPE = 'node';

	public function __construct(
		public Token $left,
		public Token $operator,
		public Token $right,
		private Context $context,
	) {}

	public function get(): string
	{
		[$referenceToken, $valueToken] = $this->normalize();
		$negated = $this->isNegated();
		$field = $this->field($referenceToken);
		$condition = $field === null
			? $this->indexCondition($valueToken)
			: $this->containsCondition($field, $valueToken);

		return $negated ? "NOT ({$condition})" : $condition;
	}

	/** @return array{0: Token, 1: Token} */
	private function normalize(): array
	{
		if ($this->left->type === TokenType::Reference) {
			return [$this->left, $this->right];
		}

		return [$this->right, $this->left];
	}

	private function isNegated(): bool
	{
		return match ($this->operator->type) {
			TokenType::Equal, TokenType::In => false,
			TokenType::Unequal, TokenType::NotIn => true,
			default => throw new ParserOutputException(
				$this->operator,
				'Reference expressions support the =, !=, @ and !@ operators only.',
			),
		};
	}

	/** The referencing field, or null when the whole node is meant. */
	private function field(#[\SensitiveParameter] Token $token): ?string
	{
		$parts = explode('.', $token->lexeme);

		if (count($parts) === 1) {
			return null;
		}

		if (count($parts) !== 2 || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $parts[1]) !== 1) {
			throw new ParserOutputException(
				$token,
				'Invalid reference selector. Use references or references.<field>.',
			);
		}

		return $parts[1];
	}

	private function indexCondition(#[\SensitiveParameter] Token $valueToken): string
	{
		// Safe SQL fragment: table() validates the hardcoded identifier and configured prefix.
		$table = $this->context->config->db->table('node_references', $this->context->db->getPdoDriver());
		$uids = $this->uids($valueToken);
		$targets = count($uids) === 1
			? '= ' . $this->context->db->quote($uids[0])
			: 'IN (' . implode(', ', array_map($this->context->db->quote(...), $uids)) . ')';

		return sprintf(
			'EXISTS (SELECT 1 FROM %s r WHERE r.owner_type = %s AND r.owner_uid = n.uid AND r.target_uid %s)',
			$table,
			$this->context->db->quote(self::OWNER_TYPE),
			$targets,
		);
	}

	private function containsCondition(string $field, #[\SensitiveParameter] Token $valueToken): string
	{
		$conditions = array_map(
			fn(string $uid): string => 'n.content @> '
			. $this->context->db->quote($this->containment($field, $uid)),
			$this->uids($valueToken),
		);

		return count($conditions) === 1 ? $conditions[0] : '(' . implode(' OR ', $conditions) . ')';
	}

	/** Reference targets are stored language-neutrally as an ordered `{uid}` list. */
	private function containment(string $field, #[\SensitiveParameter] string $uid): string
	{
		return json_encode(
			[$field => ['value' => [Field::NEUTRAL_LOCALE => [['uid' => $uid]]]]],
			JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
		);
	}

	/** @return non-empty-list<string> */
	private function uids(#[\SensitiveParameter] Token $token): array
	{
		$uids = match ($token->type) {
			TokenType::String, TokenType::Number => [$token->lexeme],
			TokenType::List => $token->items,
			default => throw new ParserOutputException(
				$token,
				'Reference comparisons only support uid literals.',
			),
		};

		if ($uids === []) {
			throw new ParserOutputException($token, 'Reference comparisons need at least one uid.');
		}

		return $uids;
	}
}
