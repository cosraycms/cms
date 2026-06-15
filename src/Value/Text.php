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
		return escape($this->unwrap());
	}

	public function unwrap(): string
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

	public function strip(array|string|null $allowed = null): string
	{
		/**
		 * As of now (early 2023), psalm does not support the
		 * type array as arguments to strip_tags's $allowed_tags.
		 *
		 * @psalm-suppress PossiblyInvalidArgument
		 */
		return strip_tags((string) $this->unwrap(), $allowed);
	}

	public function json(): mixed
	{
		return $this->unwrap();
	}

	public function isset(): bool
	{
		return $this->unwrap() ?? null ? true : false;
	}
}
