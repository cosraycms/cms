<?php

declare(strict_types=1);

namespace Celemas\Cms\Field;

use Celemas\Cms\Validation\Shapes;
use Celemas\Cms\Value\Code as CodeValue;
use Celemas\Sire\Shape;

class Code extends Field implements Capability\Translatable, Capability\SyntaxAware
{
	use Capability\IsTranslatable;
	use Capability\IsSyntaxAware;

	public function value(): CodeValue
	{
		return new CodeValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		$syntax = $this->valueContext->data['syntax'] ?? $this->getDefaultSyntax();

		if (is_array($value) && array_key_exists('value', $value)) {
			$syntax = is_string($value['syntax'] ?? null) ? $value['syntax'] : $syntax;
			$value = $value['value'];
		}

		$result = $this->getTranslatableStructure('code', $value);
		$result['syntax'] = $syntax;

		return $result;
	}

	public function shape(): Shape
	{
		$shape = Shapes::create();
		$shape->add('type', 'string')->rules('required', 'in:code');
		$shape->add('syntax', 'string')->rules('required', 'in:' . implode(',', $this->getSyntaxes()));

		if ($this->translate) {
			$locales = $this->owner->locales();
			$defaultLocale = $locales->getDefault()->id;
			$i18nShape = Shapes::create();

			foreach ($locales as $locale) {
				$localeValidators = [];

				if ($this->isRequired() && $locale->id === $defaultLocale) {
					$localeValidators[] = 'required';
				}

				$localeField = $i18nShape->add($locale->id, 'string')->rules(...$localeValidators);

				if (!in_array('required', $localeValidators, true)) {
					$localeField->optional()->nullable();
				}
			}

			$value = $shape->add('value', $i18nShape)->rules(...$this->validators);
		} else {
			$value = $shape->add('value', 'string')->rules(...$this->validators);
		}

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		return $shape;
	}

	public function properties(): array
	{
		$result = parent::properties();
		$result['syntaxes'] = $this->getSyntaxes();

		return $result;
	}
}
