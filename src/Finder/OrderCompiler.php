<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Closure;
use Cosray\Context;
use Cosray\Exception\ParserException;

final class OrderCompiler
{
	use CompilesField;

	/** @var array<string, string> */
	private array $params = [];

	public function __construct(
		private readonly array $builtins = [],
		private readonly ?Context $context = null,
	) {}

	public function compile(string|Order ...$statements): string
	{
		$expressions = [];

		foreach ($statements as $statement) {
			if ($statement instanceof Order) {
				$expressions[] = $this->structured($statement);
				continue;
			}

			if (trim($statement) === '') {
				throw new ParserException('Empty order by clause');
			}

			foreach ($this->parse($statement) as $field) {
				$fieldName = $field['field'];
				$expression = $this->builtin($fieldName)
					?? $this->compileField($fieldName, 'n.content', localeIds: $this->localeIds());
				$expressions[] = $expression . ' ' . $field['direction'];
			}
		}

		if ($expressions === []) {
			throw new ParserException('Empty order by clause');
		}

		return "\n    " . implode(",\n    ", $expressions);
	}

	/**
	 * Bound values the compiled order refers to.
	 *
	 * @return array<string, string>
	 */
	public function params(): array
	{
		return $this->params;
	}

	private function structured(Order $order): string
	{
		$field = $order->field;

		if ($field->type === 'position') {
			return $this->position($field->collection, strtoupper($order->direction));
		}

		$expression = $this->builtin($field->name);

		if ($expression !== null && $field->type !== 'text') {
			throw new ParserException("Built-in sort field '{$field->name}' already has a native type");
		}

		if ($expression === null) {
			$expression = $this->scalar($field->name);

			if ($field->type !== 'text') {
				// PostgreSQL accepts relative dates and special numeric values. Stored
				// CMS values must be absolute, finite scalars; corrupt data must fail.
				$pattern = match ($field->type) {
					'numeric' => '^[+-]?([0-9]+([.][0-9]*)?|[.][0-9]+)([eE][+-]?[0-9]+)?$',
					'date' => '^[0-9]{4}-[0-9]{2}-[0-9]{2}$',
					'timestamptz'
						=> '^[0-9]{4}-[0-9]{2}-[0-9]{2}T([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9](Z|[+-]([01][0-9]|2[0-3]):[0-5][0-9])$',
				};
				$expression =
					"CAST(CASE WHEN {$expression} ~ '{$pattern}' THEN {$expression} "
					. "ELSE 'Invalid {$field->type} sort value for {$field->name}: ' || {$expression} END AS {$field->type})";
			}
		}

		return $expression . ' ' . strtoupper($order->direction) . ' NULLS LAST';
	}

	/**
	 * A correlated lookup rather than a join, so the finder's query stays
	 * as it is. The title and uid follow in the same direction: unpositioned
	 * nodes keep the order they had before anyone arranged them.
	 */
	private function position(?string $collection, string $direction): string
	{
		if ($collection === null) {
			$scope = 'pos.parent = n.parent';
		} else {
			$param = 'order_collection_' . count($this->params);
			$this->params[$param] = $collection;
			$scope = 'pos.collection = :' . $param;
		}

		$lookup =
			'(SELECT pos.position FROM /*:cms.prefix:*/node_positions pos WHERE '
			. $scope
			. ' AND pos.node = n.node)';
		$title = $this->builtin('title');
		$terms = [$lookup . ' ' . $direction . ' NULLS LAST'];

		if ($title !== null) {
			$terms[] = $title . ' ' . $direction;
		}

		$terms[] = 'n.uid ' . $direction;

		return implode(', ', $terms);
	}

	private function scalar(string $field): string
	{
		$fields = str_contains($field, '.')
			? [$field]
			: array_map(
				static fn(string $locale): string => $field . '.' . $locale,
				$this->normalizeLocaleIds($this->localeIds()),
			);
		$values = [];

		foreach ($fields as $path) {
			// JSON_VALUE rejects arrays/objects instead of silently sorting their
			// serialized representation. SQL/JSON nulls allow locale fallback.
			$json = $this->compileField($path, 'n.content', asIs: true);
			$values[] = "NULLIF(BTRIM(JSON_VALUE({$json}, '$' RETURNING text ERROR ON ERROR), E' \\t\\n\\r\\013'), '')";
		}

		return 'COALESCE(' . implode(', ', $values) . ')';
	}

	private function builtin(string $field): ?string
	{
		$value = $this->builtins[$field] ?? null;

		return $value instanceof Closure ? $value() : $value;
	}

	private function localeIds(): array
	{
		if (!$this->context) {
			return ['zxx'];
		}

		$locale = $this->context->locale();

		return [$locale->id, ...$locale->fallbacks()];
	}

	private function parse(string $statement): array
	{
		$fields = explode(',', $statement);
		$pattern = '/^\s*([a-zA-Z][a-zA-Z0-9._]*)\s*(asc|desc)?\s*$/i';
		$result = [];

		foreach ($fields as $field) {
			if (preg_match($pattern, trim($field), $matches)) {
				$result[] = [
					'field' => $matches[1],
					'direction' => strtoupper($matches[2] ?? null ?: 'ASC'),
				];
			} else {
				throw new ParserException('Invalid order by clause');
			}
		}

		return $result;
	}
}
