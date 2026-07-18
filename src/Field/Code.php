<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Shape;
use Cosray\Validation\Shapes;
use Cosray\Value\Code as CodeValue;

class Code extends Text implements Capability\SyntaxAware
{
	use Capability\IsSyntaxAware;

	public function control(): Control
	{
		return Control::code();
	}

	public function value(): CodeValue
	{
		return new CodeValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		$syntax =
			$this->valueContext->data['meta']['syntax'][self::NEUTRAL_LOCALE] ?? $this->getDefaultSyntax();

		if (is_array($value) && array_key_exists('value', $value)) {
			$syntax = is_string($value['meta']['syntax'][self::NEUTRAL_LOCALE] ?? null)
				? $value['meta']['syntax'][self::NEUTRAL_LOCALE]
				: $syntax;
			$value = $value['value'];
		}

		$result = $this->getTranslatableStructure('code', $value);
		$result['meta']['syntax'] = [self::NEUTRAL_LOCALE => $syntax];

		return $result;
	}

	public function shape(): Shape
	{
		$shape = Shapes::create();
		$this->addType($shape);

		if ($this->isTranslatable()) {
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
			$value = $shape->add('value', $this->zxxShape('string', $this->validators));
		}

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		$meta = Shapes::create();
		$meta->add('syntax', $this->zxxShape('string', ['in:' . implode(',', $this->getSyntaxes())]));
		$shape->add('meta', $meta)->optional()->nullable();

		return $shape;
	}

	public function properties(): array
	{
		$result = parent::properties();
		$result['syntaxes'] = $this->getSyntaxes();

		return $result;
	}
}
