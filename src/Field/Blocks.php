<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celemas\Sire\Extra;
use Celemas\Sire\Shape;
use Cosray\Block\Type;
use Cosray\Schema\TranslateMode;
use Cosray\Validation\BlockValidator;
use Cosray\Validation\Prepare;
use Cosray\Validation\Shapes;
use Cosray\Value\Blocks as BlocksValue;

class Blocks extends Field implements Capability\Translatable, Capability\Blocks\Resizable
{
	use Capability\IsTranslatable;
	use Capability\Blocks\IsResizable;

	/** @var list<string> */
	protected array $allowedBlockTypes = [];

	public function control(): Control
	{
		return Control::blocks();
	}

	/**
	 * Restrict this field to the given block type ids. Without a
	 * restriction all registered, non-hidden block types are offered.
	 */
	public function allow(string ...$ids): static
	{
		$this->allowedBlockTypes = array_values(array_unique([
			...$this->allowedBlockTypes,
			...$ids,
		]));

		return $this;
	}

	public function allows(string $id): bool
	{
		return $this->allowedBlockTypes === [] || in_array($id, $this->allowedBlockTypes, true);
	}

	public function properties(): array
	{
		$result = parent::properties();
		$controls = $this->services()->controls;
		$result['blockTypes'] = array_values(array_map(
			static fn(Type $type): array => [
				'id' => $type->id(),
				'label' => $type->label(),
				'control' => $type->control()->resolve($controls)->array(),
				'init' => $type->init(),
				'hidden' => $type->hidden(),
			],
			array_filter(
				$this->services()->blocks->all(),
				fn(Type $type): bool => $this->allows($type->id()),
			),
		));

		return $result;
	}

	public function __toString(): string
	{
		return 'Blocks Field';
	}

	/** @return list<TranslateMode> */
	protected function supportedTranslateModes(): array
	{
		return [TranslateMode::Asymmetric];
	}

	public function value(): BlocksValue
	{
		return new BlocksValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		$value ??= $this->default;

		if (is_array($value)) {
			$valueMap = $this->isAsymmetricallyTranslated()
				? $value
				: [self::NEUTRAL_LOCALE => $value];
		} else {
			$valueMap = $this->emptyValueMap();
		}

		return [
			'type' => $this::class,
			'value' => $valueMap,
			'meta' => [
				'columns' => [self::NEUTRAL_LOCALE => $this->columns],
				'minCellWidth' => [self::NEUTRAL_LOCALE => $this->minCellWidth],
			],
		];
	}

	public function shape(): Shape
	{
		$shape = Shapes::create();
		$this->addType($shape);

		$richtext = $this->owner->config()->richtext;
		$itemShape = new BlockValidator(
			list: true,
			title: $this->label,
			keepUnknown: true,
			richtextClasses: $richtext->classes,
			richtextStyles: $richtext->styles,
		);

		if ($this->isAsymmetricallyTranslated()) {
			$locales = $this->owner->locales();
			$defaultLocale = $locales->getDefault()->id;
			$i18nShape = Shapes::create();

			foreach ($locales as $locale) {
				$innerValidators = [];

				if ($this->isRequired() && $locale->id === $defaultLocale) {
					$innerValidators[] = 'required';
				}

				$localeField = $i18nShape
					->add($locale->id, $itemShape)
					->rules(...$innerValidators)
					->prepare(Prepare::nullAsEmpty(...));

				if (!in_array('required', $innerValidators, true)) {
					$localeField->optional()->nullable();
				}
			}

			$value = $shape
				->add('value', $i18nShape)
				->rules(...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		} else {
			$value = $shape
				->add('value', $this->zxxShape($itemShape, $this->validators))
				->prepare(Prepare::nullAsEmpty(...));
		}

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		$meta = Shapes::create()->extra(Extra::Allow);
		$meta->add('columns', $this->zxxShape('int'))->optional()->nullable();
		$meta->add('minCellWidth', $this->zxxShape('int'))->optional()->nullable();
		$shape->add('meta', $meta)->optional()->nullable();

		return $shape;
	}

	private function emptyValueMap(): array
	{
		if (!$this->isAsymmetricallyTranslated()) {
			return [self::NEUTRAL_LOCALE => []];
		}

		$result = [];

		foreach ($this->owner->locales() as $locale) {
			$result[$locale->id] = [];
		}

		return $result;
	}
}
