<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Cosray\Exception\ParserException;

/** A structured order term always places absent values last, in either direction. */
final readonly class Order
{
	public SortField $field;
	public string $direction;

	public function __construct(string|SortField $field, string $direction = 'asc')
	{
		$this->field = is_string($field) ? SortField::text($field) : $field;
		$this->direction = strtolower(trim($direction));

		if (!in_array($this->direction, ['asc', 'desc'], true)) {
			throw new ParserException("Invalid sort direction '{$direction}'");
		}
	}
}
