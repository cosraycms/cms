<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Cosray\Exception\ParserException;

final readonly class SortField
{
	private function __construct(
		public string $name,
		public string $type,
	) {
		if (preg_match('/^[a-zA-Z][a-zA-Z0-9_]*(?:\.[a-zA-Z0-9_-]+)*$/D', $name) !== 1) {
			throw new ParserException("Invalid sort field '{$name}'");
		}
	}

	public static function text(string $name): self
	{
		return new self($name, 'text');
	}

	/** Numbers and decimal strings are compared without losing decimal precision. */
	public static function number(string $name): self
	{
		return new self($name, 'numeric');
	}

	public static function date(string $name): self
	{
		return new self($name, 'date');
	}

	public static function dateTime(string $name): self
	{
		return new self($name, 'timestamptz');
	}
}
