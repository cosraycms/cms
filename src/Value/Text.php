<?php

declare(strict_types=1);

namespace Celemas\Cms\Value;

use Celemas\Cms\Field\Capability\Translatable;
use Celemas\Cms\Field\Field;
use Celemas\Cms\Field\Owner;

use function Celemas\Cms\escape;

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

		if ($this->field->isTranslatable()) {
			$locale = $this->locale;

			while ($locale) {
				$value = $this->data['value'][$locale->id] ?? null;

				if ($value) {
					$this->value = $value;

					return $value;
				}

				$locale = $locale->fallback();
			}

			$this->value = '';

			return '';
		}

		$this->value = isset($this->data['value'])
			? $this->data['value']
			: '';

		return $this->value;
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
