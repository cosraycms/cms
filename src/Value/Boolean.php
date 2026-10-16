<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Field\Checkbox;
use Cosray\Field\Field;
use Cosray\Field\Owner;
use Override;

class Boolean extends Value
{
	public readonly ?bool $value;

	public function __construct(Owner $owner, Field $field, ValueContext $context)
	{
		parent::__construct($owner, $field, $context);

		$value = $this->value();

		if (is_bool($value)) {
			$this->value = $value;
		} else {
			$this->value = $field instanceof Checkbox && $field->nullable ? null : false;
		}
	}

	#[Override]
	public function __toString(): string
	{
		return (string) $this->value;
	}

	#[Override]
	public function raw(): ?bool
	{
		return $this->value;
	}

	#[Override]
	public function json(): mixed
	{
		return $this->value;
	}

	#[Override]
	public function isset(): bool
	{
		return $this->value !== null;
	}
}
