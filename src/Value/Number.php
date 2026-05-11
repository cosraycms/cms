<?php

declare(strict_types=1);

namespace Celemas\Cms\Value;

use Celemas\Cms\Field\Field;
use Celemas\Cms\Field\Owner;

class Number extends Value
{
	public readonly ?int $value;

	public function __construct(Owner $owner, Field $field, ValueContext $context)
	{
		parent::__construct($owner, $field, $context);

		if (is_numeric($this->data['value'] ?? null)) {
			$this->value = (int) $this->data['value'];
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
