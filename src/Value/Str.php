<?php

declare(strict_types=1);

namespace Cosray\Value;

use Override;

use function Cosray\escape;

class Str extends Value
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
		$raw = $this->raw();

		return $raw === '' ? null : $raw;
	}

	#[Override]
	public function isset(): bool
	{
		return $this->raw() ? true : false;
	}
}
