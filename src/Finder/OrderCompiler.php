<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Cosray\Context;
use Cosray\Exception\ParserException;

final class OrderCompiler
{
	use CompilesField;

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
				$expression = $this->builtins[$fieldName]
					?? $this->compileField($fieldName, 'n.content', localeIds: $this->localeIds());
				$expressions[] = $expression . ' ' . $field['direction'];
			}
		}

		if ($expressions === []) {
			throw new ParserException('Empty order by clause');
		}

		return "\n    " . implode(",\n    ", $expressions);
	}

	private function structured(Order $order): string
	{
		$field = $order->field;
		$expression = $this->builtins[$field->name] ?? null;

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
			$values[] = "NULLIF(BTRIM(JSON_VALUE({$json}, '$' RETURNING text ERROR ON ERROR)), '')";
		}

		return 'COALESCE(' . implode(', ', $values) . ')';
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
