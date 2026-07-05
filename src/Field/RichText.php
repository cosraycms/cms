<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celemas\Sire\Shape;
use Cosray\Richtext\Envelope;
use Cosray\Validation\RichtextDoc;
use Cosray\Validation\Shapes;
use Cosray\Value\RichText as RichTextValue;

class RichText extends Text
{
	public function control(): Control
	{
		return Control::richtext();
	}

	public function value(): RichTextValue
	{
		return new RichTextValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		return $this->getTranslatableStructure('richtext', $value);
	}

	/**
	 * Saves are writer-strict: the panel submits the structured
	 * envelope (format, version, per-locale documents); legacy HTML
	 * strings are rejected. See docs/richtext-format.md.
	 */
	public function shape(): Shape
	{
		$shape = Shapes::create();
		$this->addType($shape);
		$shape->add('format', 'string')->rules('required', 'in:' . Envelope::FORMAT);
		// `in` compares strictly against string args, so pin the int
		// version with a min/max pair instead.
		$shape->add('version', 'int')->rules(
			'required',
			'min:' . Envelope::VERSION,
			'max:' . Envelope::VERSION,
		);

		$richtext = $this->owner->config()->richtext;
		$doc = new RichtextDoc($richtext->classes, $richtext->styles);

		if ($this->isTranslatable()) {
			$locales = $this->owner->locales();
			$defaultLocale = $locales->getDefault()->id;
			$i18nShape = Shapes::create();

			foreach ($locales as $locale) {
				$localeValidators = [];

				if ($this->isRequired() && $locale->id === $defaultLocale) {
					$localeValidators[] = 'required';
				}

				$localeField = $i18nShape->add($locale->id, $doc)->rules(...$localeValidators);

				if (!in_array('required', $localeValidators, true)) {
					$localeField->optional()->nullable();
				}
			}

			$value = $shape->add('value', $i18nShape)->rules(...$this->validators);
		} else {
			$value = $shape->add('value', $this->zxxShape($doc, $this->validators));
		}

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		$this->addMeta($shape);

		return $shape;
	}
}
