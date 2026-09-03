<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Extra;
use Celema\Sire\Review;
use Celema\Sire\Shape;
use Cosray\Block\Layout;
use Cosray\Contract\Block;
use Cosray\Exception\RuntimeException;
use Cosray\Schema\Tools;
use Cosray\Schema\TranslateMode;
use Cosray\Validation\Prepare;
use Cosray\Validation\Shapes;
use Cosray\Value\Blocks as BlocksValue;

/**
 * A list of typed rows with a grid layout. Block types are classes
 * implementing Cosray\Contract\Block; without `#[Allows]` the field
 * offers the registry's default list, without `#[Columns]` it is a
 * stacked one-column list.
 */
class Blocks extends Field implements Capability\Translatable, Capability\Blocks\Resizable, Capability\ToolsAware
{
	use Capability\IsTranslatable;
	use Capability\Blocks\IsResizable;
	use Capability\IsToolsAware;
	use RowTypes;

	/** @var list<class-string<Block>> */
	protected array $allowedBlockTypes = [];

	public function control(): Control
	{
		return Control::blocks()
			->prop('blockTypes', array_map($this->blockTypeProperties(...), $this->allowedBlockTypes()))
			->prop('columns', $this->columns)
			->prop('min', $this->min)
			->prop('responsive', $this->responsive->value)
			->prop('meta', $this->blockMetaControl());
	}

	/**
	 * The block meta dialog: the same group for every block type, patched
	 * on save like a field's meta.
	 */
	protected function blockMetaControl(): Control
	{
		return Control::group([
			['key' => 'class', 'label' => __('block:class'), 'control' => Control::text()],
			['key' => 'id', 'label' => __('block:id'), 'control' => Control::text()],
		]);
	}

	/** @param class-string<Block> ...$types */
	public function allow(string ...$types): static
	{
		foreach ($types as $type) {
			if (!class_exists($type)) {
				throw new RuntimeException("Blocks field '{$this->name}' allows unknown block type '{$type}'");
			}

			if (!is_a($type, Block::class, true)) {
				throw new RuntimeException(
					"Blocks field '{$this->name}' block type '{$type}' must implement " . Block::class,
				);
			}
		}

		$this->allowedBlockTypes = array_values(array_unique([
			...$this->allowedBlockTypes,
			...$types,
		]));

		return $this;
	}

	public function allows(string $type): bool
	{
		return in_array($type, $this->allowedBlockTypes(), true);
	}

	/**
	 * The types this field offers: the `#[Allows]` list, otherwise the
	 * registry's default list.
	 *
	 * @return list<class-string<Block>>
	 */
	public function allowedBlockTypes(): array
	{
		return $this->allowedBlockTypes === [] ? $this->services()->blocks->all() : $this->allowedBlockTypes;
	}

	/** @return array<string, Field> */
	public function blockFields(?string $type = null): array
	{
		$type ??= $this->allowedBlockTypes()[0]
			?? throw new RuntimeException("Blocks field '{$this->name}' offers no block types");

		return $this->rowFieldsFor($type);
	}

	/**
	 * @param class-string<Block> $type
	 * @param array<string, mixed> $data
	 * @return array<string, Field>
	 */
	public function blockFieldsFor(string $type, array $data = []): array
	{
		return $this->rowFieldsFor($type, $data);
	}

	/**
	 * The `data-type` value of a block type: its `#[Handle]`, otherwise
	 * derived from the class name.
	 *
	 * @param class-string<Block> $type
	 */
	public function blockHandle(string $type): string
	{
		return (string) $this->nodeTypes()->get($type, 'handle');
	}

	/** @return list<TranslateMode> */
	protected function supportedTranslateModes(): array
	{
		return [TranslateMode::Symmetric, TranslateMode::Asymmetric];
	}

	public function value(): BlocksValue
	{
		return new BlocksValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		$value ??= $this->valueContext->data['value'] ?? $this->default ?? [];

		return [
			'type' => $this::class,
			'value' => $this->structureMap(is_array($value) ? $value : []),
		];
	}

	public function shape(): Shape
	{
		$shape = Shapes::create();
		$this->addType($shape);
		$rows = $this->rowsShape();

		if ($this->isAsymmetricallyTranslated()) {
			$locales = $this->owner->locales();
			$defaultLocale = $locales->getDefault()->id;
			$i18nShape = Shapes::create();

			foreach ($locales as $locale) {
				$localeField = $i18nShape
					->add($locale->id, $rows)
					->label($this->valueLabel($locale))
					->prepare(Prepare::nullAsEmpty(...));

				if ($this->isRequired() && $locale->id === $defaultLocale) {
					$localeField->rules('required');
				} else {
					$localeField->optional()->nullable();
				}
			}

			$value = $shape
				->add('value', $i18nShape)
				->rules(...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		} else {
			$value = $shape
				->add('value', $this->zxxShape($rows, $this->validators))
				->prepare(Prepare::nullAsEmpty(...));
		}

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		$this->addMeta($shape);

		return $shape;
	}

	protected function rowKind(): string
	{
		return 'block';
	}

	/** Nested typed repeaters are rejected, as on entry types. */
	protected function assertRowField(string $type, Definition $definition): void
	{
		foreach ([Entries::class => 'entries', self::class => 'blocks'] as $class => $kind) {
			if (is_a($definition->type, $class, true)) {
				throw new RuntimeException(
					"Blocks field '{$this->name}' cannot contain nested {$kind} field"
						. " '{$definition->name}' in block type '{$type}'",
				);
			}
		}
	}

	/**
	 * The translation mode rule: only a symmetric list translates its
	 * sub-fields — a per-locale list already translates the whole block,
	 * an untranslated list nothing. The field's `#[Tools]` feeds every
	 * richtext sub-field that declares none of its own.
	 */
	protected function configureRowField(Field $field, Definition $definition): void
	{
		if ($field instanceof Capability\Translatable && !$this->isSymmetricallyTranslated()) {
			$field->translate(null);
		}

		if (
			$field instanceof Capability\ToolsAware
			&& $this->tools !== []
			&& $definition->property->getAttributes(Tools::class) === []
		) {
			$field->tools(...$this->tools);
		}
	}

	/**
	 * @param class-string<Block> $type
	 * @return array{type: class-string, handle: string, label: string, fields: list<array>, fieldsets: list<array>}
	 */
	protected function blockTypeProperties(string $type): array
	{
		$properties = $this->rowTypeProperties($type);

		return [
			'type' => $type,
			'handle' => $this->blockHandle($type),
			'label' => $properties['label'],
			'fields' => $properties['fields'],
			'fieldsets' => $properties['fieldsets'],
		];
	}

	/**
	 * A plain list lands in the shared or default-locale list; a locale
	 * map is taken per locale.
	 *
	 * @return array<string, list<array>>
	 */
	private function structureMap(array $value): array
	{
		if ($this->isAsymmetricallyTranslated()) {
			$map = array_is_list($value) ? [$this->owner->defaultLocale()->id => $value] : $value;
			$result = [];

			foreach ($this->owner->locales() as $locale) {
				$result[$locale->id] = $this->rowStructures($map[$locale->id] ?? []);
			}

			return $result;
		}

		$rows = array_is_list($value) ? $value : $value[self::NEUTRAL_LOCALE] ?? [];

		return [self::NEUTRAL_LOCALE => $this->rowStructures($rows)];
	}

	/** @return list<array> */
	private function rowStructures(mixed $rows): array
	{
		$result = [];

		foreach (is_array($rows) ? $rows : [] as $row) {
			if (!is_array($row)) {
				continue;
			}

			$type = $row['type'] ?? null;

			if (!is_string($type) || !$this->allows($type)) {
				continue;
			}

			$fields = is_array($row['fields'] ?? null) ? $row['fields'] : [];
			$structure = [
				'uid' => is_string($row['uid'] ?? null) ? $row['uid'] : null,
				'type' => $type,
				'layout' => Layout::normalize($row['layout'] ?? null, $this->columns, $this->min)->array(),
				'fields' => $this->rowStructure($type, $fields),
			];

			if (is_array($row['meta'] ?? null) && $row['meta'] !== []) {
				$structure['meta'] = $row['meta'];
			}

			$result[] = $structure;
		}

		return $result;
	}

	/**
	 * One row: uid, an allowed type, a layout inside the field's bounds
	 * (out-of-range values are rejected, not clamped, so a programmatic
	 * write fails loudly), the type's fields and the block meta.
	 */
	private function rowsShape(): Shape
	{
		$layout = Shapes::create();
		$layout->add('span', 'int')->rules('required', "min:{$this->min}", "max:{$this->columns}");
		$layout->add('rows', 'int')->rules('required', 'min:1', 'max:' . Layout::MAX_ROWS);
		$layout->add('indent', 'int')->rules('required', 'min:0', 'max:' . ($this->columns - $this->min));

		$rows = Shapes::list();
		$rows->add('uid', 'string')->rules('required');
		$rows
			->add('type', 'string')
			->rules('required', 'in:' . implode(',', $this->allowedBlockTypes()));
		$rows->add('layout', $layout)->rules('required');
		$rows
			->add('fields', Shapes::create())
			->rules('required')
			->finalize($this->finalizeRowFields(...));
		$rows->add('meta', Shapes::create()->extra(Extra::Allow))->optional()->nullable();
		$rows->review($this->reviewRows(...));

		return $rows;
	}

	private function reviewRows(Review $review): void
	{
		$this->reviewRowFields($review);

		foreach ($review->values() as $index => $row) {
			if (((int) $row['layout']['span'] + (int) $row['layout']['indent']) > $this->columns) {
				$review->addError([$index, 'layout', 'indent'], __('block:invalid-indent'));
			}
		}
	}
}
