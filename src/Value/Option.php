<?php

declare(strict_types=1);

namespace Cosray\Value;

use Override;

use function Cosray\escape;

class Option extends Value
{
	#[Override]
	public function __toString(): string
	{
		return escape($this->raw());
	}

	#[Override]
	public function raw(): string
	{
		$value = $this->value();

		return is_string($value) || is_numeric($value) ? (string) $value : '';
	}

	#[Override]
	public function json(): ?string
	{
		return $this->isset() ? $this->raw() : null;
	}

	#[Override]
	public function isset(): bool
	{
		return $this->raw() !== '';
	}
}
