<?php

declare(strict_types=1);

namespace Cosray\Value;

use function Cosray\escape;

class Str extends Value
{
	public function __toString(): string
	{
		return escape($this->unwrap());
	}

	public function unwrap(): string
	{
		$value = $this->value();

		return is_string($value) || is_numeric($value) ? (string) $value : '';
	}

	public function json(): string
	{
		return $this->unwrap();
	}

	public function isset(): bool
	{
		return $this->unwrap() ? true : false;
	}
}
