<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Field\Field;
use Cosray\Field\Owner;

class Number extends Value
{
	public readonly ?int $value;

	public function __construct(Owner $owner, Field $field, ValueContext $context)
	{
		parent::__construct($owner, $field, $context);

		$value = $this->value();

		if (is_numeric($value)) {
			$this->value = (int) $value;
		} else {
			$this->value = null;
		}
	}

	public function __toString(): string
	{
		if ($this->value === null) {
			return '';
		}

		return (string) $this->value;
	}

	public function json(): ?int
	{
		return $this->unwrap();
	}

	public function unwrap(): ?int
	{
		return $this->value;
	}

	public function isset(): bool
	{
		return isset($this->value) ? true : false;
	}
}
