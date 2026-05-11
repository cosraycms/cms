<?php

declare(strict_types=1);

namespace Celemas\Cms\Value;

use Celemas\Cms\Field\Field;
use Celemas\Cms\Field\Owner;

class Boolean extends Value
{
	public readonly bool $value;

	public function __construct(Owner $owner, Field $field, ValueContext $context)
	{
		parent::__construct($owner, $field, $context);

		if (is_bool($this->data['value'] ?? null)) {
			$this->value = $this->data['value'];
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
