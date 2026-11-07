<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Shape;
use Cosray\LinkTarget;
use Cosray\Validation\Shapes;
use Cosray\Value\Link as LinkValue;
use Override;

/**
 * One link target: an address, a page or a file, with an optional new
 * tab and `rel`. Neutral by default, as a page link follows the
 * visitor's language; #[Translate] gives each language its own target.
 */
class Link extends Field implements Capability\Translatable
{
	use Capability\IsTranslatable;

	#[Override]
	public static function references(array $field): array
	{
		$references = ['assets' => [], 'nodes' => []];

		foreach (is_array($field['value'] ?? null) ? $field['value'] : [] as $link) {
			if (is_array($link)) {
				$found = LinkTarget::references($link);
				$references['assets'] = [...$references['assets'], ...$found['assets']];
				$references['nodes'] = [...$references['nodes'], ...$found['nodes']];
			}
		}

		return $references;
	}

	#[Override]
	public function control(): Control
	{
		return Control::link();
	}

	#[Override]
	public function value(): LinkValue
	{
		return new LinkValue($this->owner, $this, $this->valueContext);
	}

	/** A single link as the value applies to the default language, or to all of them when neutral. */
	#[Override]
	public function structure(mixed $value = null): array
	{
		$value ??= $this->default;

		if (is_array($value) && array_intersect_key($value, array_flip(LinkTarget::KEYS)) !== []) {
			$value = $this->isTranslatable()
				? [$this->owner->defaultLocale()->id => $value]
				: [self::NEUTRAL_LOCALE => $value];
		}

		return $this->getTranslatableStructure('link', $value);
	}

	#[Override]
	public function shape(): Shape
	{
		$shape = Shapes::create();
		$this->addType($shape);

		if ($this->isTranslatable()) {
			$defaultLocale = $this->owner->locales()->getDefault()->id;
			$i18nShape = Shapes::create();

			foreach ($this->owner->locales() as $locale) {
				$localeField = $i18nShape->add($locale->id, LinkTarget::shape())->label($this->valueLabel($locale));

				if ($this->isRequired() && $locale->id === $defaultLocale) {
					$localeField->rules('required');
				} else {
					$localeField->optional()->nullable();
				}
			}

			$value = $shape->add('value', $i18nShape)->rules(...$this->validators);
		} else {
			$value = $shape->add('value', $this->zxxShape(LinkTarget::shape(), $this->validators));
		}

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		$this->addMeta($shape);

		return $shape;
	}
}
