<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Extra;
use Celema\Sire\Review;
use Celema\Sire\Shape;
use Cosray\Richtext\Inline;
use Cosray\Schema\TranslateMode;
use Cosray\Validation\Prepare;
use Cosray\Validation\Shapes;
use Cosray\Value;
use Override;

class File extends Field implements Capability\Limitable, Capability\Translatable, Capability\ItemRequirable
{
	use Capability\IsLimitable;
	use Capability\IsTranslatable;
	use Capability\IsItemRequirable;

	private const array ITEM_LABELS = [
		'alt' => 'image:alt-text-long',
		'caption' => 'image:caption',
		'attribution' => 'image:attribution',
		'link' => 'image:link',
	];

	#[Override]
	public function control(): Control
	{
		return Control::file();
	}

	/** @return list<TranslateMode> */
	protected function supportedTranslateModes(): array
	{
		return [TranslateMode::Symmetric, TranslateMode::Asymmetric];
	}

	#[Override]
	public function value(): Value\File|Value\Files
	{
		if ($this->allowsMultipleItems()) {
			if ($this->isAsymmetricallyTranslated()) {
				return new Value\TranslatedFiles($this->owner, $this, $this->valueContext);
			}

			return new Value\Files($this->owner, $this, $this->valueContext);
		}

		if ($this->isAsymmetricallyTranslated()) {
			return new Value\TranslatedFile($this->owner, $this, $this->valueContext);
		}

		return new Value\File($this->owner, $this, $this->valueContext);
	}

	#[Override]
	public function structure(mixed $value = null): array
	{
		if ($this->isAsymmetricallyTranslated()) {
			return $this->getTranslatableFileStructure('file', $value);
		}

		return $this->getFileStructure('file', $value);
	}

	#[Override]
	public function properties(): array
	{
		$properties = parent::properties();

		return $this->requiredItems === [] ? $properties : [...$properties, 'requiredItems' => $this->requiredItems];
	}

	#[Override]
	public function shape(): Shape
	{
		$limitValidators = $this->limitValidators();
		$shape = Shapes::create();
		$this->addType($shape);

		if ($this->isAsymmetricallyTranslated()) {
			$i18nShape = Shapes::create();
			$locales = $this->owner->locales();
			$defaultLocale = $locales->getDefault()->id;

			foreach ($locales as $locale) {
				$localeValidators = $limitValidators;
				$localeField = $i18nShape
					->add($locale->id, $this->fileListShape($locale->id === $defaultLocale))
					->label($this->valueLabel($locale))
					->prepare(Prepare::nullAsEmpty(...));

				if ($this->isRequired() && $locale->id === $defaultLocale) {
					$localeValidators[] = 'required';
				} else {
					$localeField->optional()->nullable();
				}

				$localeField->rules(...$localeValidators);
			}

			$value = $shape
				->add('value', $i18nShape)
				->rules(...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		} else {
			$value = $shape
				->add('value', $this->zxxShape($this->fileListShape(), $limitValidators))
				->rules(...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		}

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		$this->addMeta($shape);

		return $shape;
	}

	/** A list in another language than the default may go without what its items require. */
	protected function fileListShape(bool $complete = true): Shape
	{
		$fileShape = Shapes::list();
		$fileShape->add('uid', 'string')->rules('required');
		$fileShape->add('meta', $this->itemMetaShape())->optional()->nullable();

		if ($complete && $this->requiredItems !== []) {
			$fileShape->review($this->reviewRequiredItems(...));
		}

		return $fileShape;
	}

	private function reviewRequiredItems(Review $review): void
	{
		$multiple = $this->allowsMultipleItems();

		foreach ($review->values() as $index => $item) {
			foreach ($this->requiredItems as $key) {
				if ($this->itemHas($key, is_array($item) ? $item : [])) {
					continue;
				}

				$params = ['field' => $this->valueLabel(), 'label' => __(self::ITEM_LABELS[$key] ?? $key)];
				$review->addError(
					[$index, 'meta', $key],
					$multiple
						? __('media:item-missing-at', [...$params, 'position' => (int) $index + 1])
						: __('media:item-missing', $params),
				);
			}
		}
	}

	/**
	 * Whether an item has a part, its own or from the catalog, as the site would
	 * print that. A translated text only has to exist in the default language.
	 *
	 * @param array<mixed> $item
	 */
	private function itemHas(string $key, array $item): bool
	{
		$meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];

		if ($key === 'link') {
			return is_array($meta['link'] ?? null);
		}

		$uid = is_string($item['uid'] ?? null) ? $item['uid'] : '';
		$catalog = $this->owner->assets()->get($uid)->meta ?? [];

		if ($key === 'attribution') {
			return Inline::text($meta[$key] ?? null) !== '' || Inline::text($catalog[$key] ?? null) !== '';
		}

		$default = $this->owner->locales()->getDefault()->id;
		$own = is_array($meta[$key] ?? null) ? $meta[$key] : [];
		$shared = is_array($catalog[$key] ?? null) ? $catalog[$key] : [];

		return (
			Inline::text($own[$default] ?? null) !== ''
				|| Inline::text($own[self::NEUTRAL_LOCALE] ?? null) !== ''
				|| Inline::text($shared[$default] ?? null) !== ''
		);
	}

	protected function itemMetaShape(): Shape
	{
		$shape = Shapes::create()->extra(Extra::Allow);
		$shape->review(static function (Review $review): void {
			$captions = $review->values()['caption'] ?? null;

			foreach (is_array($captions) ? $captions : [] as $locale => $caption) {
				if (!Inline::valid($caption)) {
					$review->addError(['caption', $locale], __('image:caption-invalid'));
				}
			}
		});

		return $shape;
	}
}
