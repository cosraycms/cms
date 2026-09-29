<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Cosray\Collection\Schemas;
use Cosray\Exception\ParserException;

final readonly class SortField
{
	private function __construct(
		public string $name,
		public string $type,
		public ?string $collection = null,
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

	/**
	 * The manual order editors arrange in the panel: among the children of
	 * the node's parent, or with a collection (class or handle) at that
	 * collection's top level. Nodes without a position follow the
	 * positioned ones by title, and the uid breaks remaining ties.
	 */
	public static function position(?string $collection = null): self
	{
		if ($collection === null) {
			return new self('position', 'position');
		}

		$collection = trim($collection);

		if ($collection === '') {
			throw new ParserException('A collection position needs a collection');
		}

		if (class_exists($collection)) {
			$collection = (string) new Schemas()->of($collection)->handle;
		}

		return new self('position', 'position', $collection);
	}
}
