<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/**
 * Conditional field visibility: the field is only active while the
 * referenced sibling field's value satisfies the condition.
 *
 *     #[When('multi_day')]                    truthy
 *     #[When('layout', 'hero')]               equality
 *     #[When('template', in: ['a', 'b'])]     membership
 *     #[When('teaser', op: 'empty')]          explicit operator
 *
 * The value of an inactive field is kept in the database — the editor
 * merely hides it and the frontend presents it as empty (read-time
 * enforcement); `Field::raw()` bypasses deliberately. Condition sources
 * are limited to primitive, non-translated fields.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class When
{
	public function __construct(
		public readonly string $field,
		public readonly string|int|float|bool|null $value = null,
		public readonly ?array $in = null,
		public readonly string $op = '',
	) {}

	/** @return array{field: string, op: string, value: mixed} */
	public function condition(): array
	{
		if ($this->in !== null) {
			return ['field' => $this->field, 'op' => 'in', 'value' => array_values($this->in)];
		}

		if ($this->op !== '') {
			return ['field' => $this->field, 'op' => $this->op, 'value' => $this->value];
		}

		if ($this->value !== null) {
			return ['field' => $this->field, 'op' => 'eq', 'value' => $this->value];
		}

		return ['field' => $this->field, 'op' => 'truthy', 'value' => null];
	}
}
