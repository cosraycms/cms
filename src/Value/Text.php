<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Field\Capability\Translatable;
use Cosray\Field\Field;
use Cosray\Field\Owner;

use function Cosray\escape;

/**
 * @property-read Field&Translatable $field
 */
class Text extends Value
{
	protected string $value;

	public function __construct(
		Owner $owner,
		Field&Translatable $field,
		ValueContext $context,
	) {
		parent::__construct($owner, $field, $context);
	}

	public function __toString(): string
	{
		return escape($this->raw());
	}

	public function raw(): string
	{
		if (isset($this->value)) {
			return $this->value;
		}

		$value = $this->value();

		if (is_string($value) || is_numeric($value)) {
			$this->value = (string) $value;

			return $this->value;
		}

		$this->value = '';

		return '';
	}

	/** @param list<string>|string|null $allowed */
	public function strip(array|string|null $allowed = null): string
	{
		return strip_tags((string) $this->raw(), $allowed);
	}

	public function json(): mixed
	{
		return $this->raw();
	}

	public function isset(): bool
	{
		return (bool) $this->raw();
	}
}
