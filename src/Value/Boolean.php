<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Field\Field;
use Cosray\Field\Owner;

class Boolean extends Value
{
	public readonly bool $value;

	public function __construct(Owner $owner, Field $field, ValueContext $context)
	{
		parent::__construct($owner, $field, $context);

		$value = $this->value();

		if (is_bool($value)) {
			$this->value = $value;
		} else {
			$this->value = false;
		}
	}

	public function __toString(): string
	{
		return (string) $this->value;
	}

	public function unwrap(): bool
	{
		return $this->value;
	}

	public function json(): mixed
	{
		return $this->value;
	}

	public function isset(): bool
	{
		return true;
	}
}
