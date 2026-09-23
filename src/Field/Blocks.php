<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Extra;
use Celema\Sire\Review;
use Celema\Sire\Shape;
use Cosray\Block\Layout;
use Cosray\Block\Placement;
use Cosray\Contract\Block;
use Cosray\Exception\RuntimeException;
use Cosray\Schema\Tool;
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
 *
 * The column count is stored with the value (`columns` next to `value`
 * and `meta`): the blocks' positions only mean something on the grid
 * they were placed on. `#[Columns]` is the default a new value starts
 * with, so changing it leaves stored content alone.
 */
class Blocks extends Field implements
	Capability\Translatable,
	Capability\Blocks\Resizable,
	Capability\ToolsAware,
	Capability\Searchable
{
	use Capability\IsSearchable;
	use Capability\IsTranslatable;
	use Capability\Blocks\IsResizable;
	use Capability\IsToolsAware;
	use RowTypes;

	public const int COMMON_LIMIT = 6;
	public const int MAX_COLUMNS = 25;

	/** The spacing tokens the gap and padding settings choose from; unset means the site's default. */
	public const array SPACING = ['none', 's', 'm', 'l', 'xl'];

	/** @var list<class-string<Block>> */
	protected array $allowedBlockTypes = [];

	/** @var list<class-string<Block>> */
	protected array $commonTypes = [];

	public function control(): Control
	{
		return Control::blocks()
			->prop('commonTypes', self::commonSelection($this->commonTypes, $this->allowedBlockTypes(), $this->name))
			->prop('blockTypes', array_map($this->blockTypeProperties(...), $this->allowedBlockTypes()))
			->prop('columns', $this->columns)
			->prop('min', $this->min)
			->prop('responsive', $this->responsive->value)
			->prop('meta', $this->blockMetaControl());
	}

	/**
	 * The field's own settings: the gap between blocks, as one value or
	 * split by axis. The panel renders the split as a toggle.
	 */
	public function metaControl(): ?Control
	{
		return Control::group([
			['key' => 'gap', 'label' => __('field:gap'), 'control' => self::spacingControl()],
			['key' => 'rowGap', 'label' => __('field:row-gap'), 'control' => self::spacingControl()],
			['key' => 'columnGap', 'label' => __('field:column-gap'), 'control' => self::spacingControl()],
		]);
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
			['key' => 'padding', 'label' => __('field:padding'), 'control' => self::spacingControl()],
		]);
	}

	/** An option control over the spacing tokens, the site's default first. */
	public static function spacingControl(): Control
	{
		$labels = [
			'none' => __('field:spacing-none'),
			's' => __('field:spacing-s'),
			'm' => __('field:spacing-m'),
			'l' => __('field:spacing-l'),
			'xl' => __('field:spacing-xl'),
		];

		return Control::option()->prop('options', [
			['value' => '', 'label' => __('field:spacing-default')],
			...array_map(
				static fn(string $size): array => ['value' => $size, 'label' => $labels[$size]],
				self::SPACING,
			),
		]);
	}

	/** Gap, row gap and column gap as spacing tokens; other meta keys pass through. */
	protected function metaShape(): Shape
	{
		$shape = parent::metaShape();

		foreach (['gap', 'rowGap', 'columnGap'] as $key) {
			$shape->add($key, $this->spacingShape())->optional()->nullable();
		}

		return $shape;
	}

	/** A neutral-locale map holding a spacing token, or nothing for the site's default. */
	private function spacingShape(): Shape
	{
		$shape = Shapes::create();
		$shape
			->add(self::NEUTRAL_LOCALE, 'string')
			->rules('in:' . implode(',', ['', ...self::SPACING]))
			->optional()
			->nullable();

		return $shape;
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

	public function common(string ...$types): static
	{
		foreach ($types as $type) {
			if (!class_exists($type)) {
				throw new RuntimeException("Blocks field '{$this->name}' has unknown common type '{$type}'");
			}

			if (!is_a($type, Block::class, true)) {
				throw new RuntimeException(
					"Blocks field '{$this->name}' common type '{$type}' must implement " . Block::class,
				);
			}
		}

		$this->commonTypes = $types;

		return $this;
	}

	/**
	 * The short menu's types in order: an explicit list checked against
	 * the allowed types, or the first six allowed ones.
	 *
	 * @param list<string> $common
	 * @param list<string> $allowed
	 * @return list<string>
	 */
	public static function commonSelection(array $common, array $allowed, string $field): array
	{
		$common = array_values(array_unique($common));

		if ($common === []) {
			return array_slice($allowed, 0, self::COMMON_LIMIT);
		}

		if (count($common) > self::COMMON_LIMIT) {
			throw new RuntimeException(
				"Blocks field '{$field}' may have at most " . self::COMMON_LIMIT . ' distinct common types',
			);
		}

		foreach ($common as $type) {
			if (!in_array($type, $allowed, true)) {
				throw new RuntimeException("Blocks field '{$field}' common type '{$type}' is not allowed");
			}
		}

		return $common;
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

	/** The stored column count, the field's default for a value without one. */
	public function columnsOf(mixed $stored): int
	{
		return self::storedColumns($stored, $this->columns);
	}

	/** A stored column count if it is one, `$default` otherwise. */
	public static function storedColumns(mixed $stored, int $default): int
	{
		return is_int($stored) && $stored >= 1 && $stored <= self::MAX_COLUMNS ? $stored : $default;
	}

	/** The narrowest block on a grid of that many columns. */
	public function minOf(int $columns): int
	{
		return min($this->min, $columns);
	}

	public function structure(mixed $value = null): array
	{
		$value ??= $this->valueContext->data['value'] ?? $this->default ?? [];
		$columns = $this->columnsOf($this->valueContext->data['columns'] ?? null);

		return [
			'type' => $this::class,
			'columns' => $columns,
			'value' => $this->structureMap(is_array($value) ? $value : [], $columns),
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

		$shape->add('columns', 'int')->optional()->rules('min:1', 'max:' . self::MAX_COLUMNS);
		$this->addMeta($shape);
		$shape->review($this->reviewGrid(...));

		return $shape;
	}

	/**
	 * The layouts against the value's own grid: every block on the field's
	 * grid inside its columns and clear of the others, every split's
	 * blocks within its area.
	 */
	private function reviewGrid(Review $review): void
	{
		$data = $review->values();
		$columns = $this->columnsOf($data['columns'] ?? null);
		$min = $this->minOf($columns);
		$lists = is_array($data['value'] ?? null) ? $data['value'] : [];

		foreach ($lists as $locale => $rows) {
			$layouts = [];

			foreach (is_array($rows) ? array_values($rows) : [] as $index => $row) {
				$layout = is_array($row) && is_array($row['layout'] ?? null) ? $row['layout'] : [];
				$area = new Layout(
					(int) ($layout['colspan'] ?? 0),
					(int) ($layout['rowspan'] ?? 1),
					(int) ($layout['col'] ?? 0),
					(int) ($layout['row'] ?? 0),
				);
				$path = ['value', $locale, $index, 'layout'];
				$layouts[] = $area;

				if ($area->colspan < $min || $area->colspan > $columns) {
					$review->addError([...$path, 'colspan'], __('block:invalid-width'));
				} elseif (
					isset($layout['col']) !== isset($layout['row'])
					|| $area->placed()
					&& ($area->col + $area->colspan - 1) > $columns
				) {
					$review->addError([...$path, 'col'], __('block:invalid-position'));
				}

				if (is_array($row) && is_array($row['blocks'] ?? null)) {
					$this->reviewSplit(
						$review,
						['value', $locale, $index, 'blocks'],
						$area,
						array_values($row['blocks']),
						$min,
					);
				}
			}

			$overlap = Placement::overlap($layouts);

			if ($overlap !== null) {
				$review->addError(['value', $locale, $overlap, 'layout', 'col'], __('block:overlap'));
			}
		}
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

		// The project's richtext.tools describes the full toolbar and does
		// not reach into blocks: without a list from the property or the
		// field, the inline preset applies.
		if (
			$field instanceof Capability\ToolsAware
			&& $definition->property->getAttributes(Tools::class) === []
		) {
			$field->tools(...$this->tools !== [] ? $this->tools : Tool::INLINE);
		}
	}

	/**
	 * @param class-string<Block> $type
	 * @return array{type: class-string, handle: string, label: string, icon: ?array, labels: bool, fields: list<array>, fieldsets: list<array>}
	 */
	protected function blockTypeProperties(string $type): array
	{
		$properties = $this->rowTypeProperties($type);
		$visible = array_filter(
			$properties['fields'],
			static fn(array $field): bool => !($field['hidden'] ?? false),
		);

		return [
			'type' => $type,
			'handle' => $this->blockHandle($type),
			'label' => $properties['label'],
			'icon' => $this->nodeTypes()->get($type, 'icon'),
			// A block with one field says what that field is; the label
			// below the block's own would only repeat it. #[Labels] keeps it.
			'labels' =>
				count($visible) !== 1
					|| (bool) $this->nodeTypes()->get($type, 'labels', false),
			'fields' => array_map(self::inBlock(...), $properties['fields']),
			'fieldsets' => $properties['fieldsets'],
		];
	}

	/**
	 * A block marks none of its fields as required and tells each of them
	 * that it renders inside a block. Reaching for a block is what makes its
	 * content mandatory, so the mark tells the editor nothing it does not
	 * already see; only the panel's view of the field loses it — the shape
	 * still validates, built from the field itself. The presentation lets an
	 * element control render as content instead of as a form control.
	 *
	 * @param array<string, mixed> $field
	 * @return array<string, mixed>
	 */
	private static function inBlock(array $field): array
	{
		unset($field['required']);
		$field['presentation'] = 'block';

		return $field;
	}

	/**
	 * A plain list lands in the shared or default-locale list; a locale
	 * map is taken per locale.
	 *
	 * @return array<string, list<array>>
	 */
	private function structureMap(array $value, int $columns): array
	{
		if ($this->isAsymmetricallyTranslated()) {
			$map = array_is_list($value) ? [$this->owner->defaultLocale()->id => $value] : $value;
			$result = [];

			foreach ($this->owner->locales() as $locale) {
				$result[$locale->id] = $this->rowStructures($map[$locale->id] ?? [], $columns);
			}

			return $result;
		}

		$rows = array_is_list($value) ? $value : $value[self::NEUTRAL_LOCALE] ?? [];

		return [self::NEUTRAL_LOCALE => $this->rowStructures($rows, $columns)];
	}

	/**
	 * Every row with its position on the grid; one without gets a place
	 * below the others.
	 *
	 * @return list<array>
	 */
	private function rowStructures(mixed $rows, int $columns): array
	{
		$result = [];
		$min = $this->minOf($columns);

		foreach (is_array($rows) ? $rows : [] as $row) {
			if (!is_array($row)) {
				continue;
			}

			$layout = Layout::normalize($row['layout'] ?? null, $columns, $min);
			$structure = !isset($row['type']) && is_array($row['blocks'] ?? null)
				? $this->splitStructure($row, $layout, $min)
				: $this->blockStructure($row, $layout);

			if ($structure !== null) {
				$result[] = $structure;
			}
		}

		return Placement::rows($result, $columns, $min);
	}

	private function blockStructure(array $row, Layout $layout): ?array
	{
		$type = $row['type'] ?? null;

		if (!is_string($type) || !$this->allows($type)) {
			return null;
		}

		$fields = is_array($row['fields'] ?? null) ? $row['fields'] : [];
		$structure = [
			'uid' => is_string($row['uid'] ?? null) ? $row['uid'] : null,
			'type' => $type,
			'layout' => $layout->array(),
			'fields' => $this->rowStructure($type, $fields),
		];

		if (is_array($row['meta'] ?? null) && $row['meta'] !== []) {
			$structure['meta'] = $row['meta'];
		}

		return $structure;
	}

	/**
	 * A split's blocks are laid out on its area and never split again.
	 * One left over after dropping disallowed types is no split: it takes
	 * the split's place, as in the editor.
	 */
	private function splitStructure(array $row, Layout $layout, int $min): ?array
	{
		$blocks = [];

		foreach ($row['blocks'] as $child) {
			if (!is_array($child)) {
				continue;
			}

			$childLayout = Layout::normalize($child['layout'] ?? null, $layout->colspan, $min, $layout->rowspan);
			$structure = $this->blockStructure($child, $childLayout);

			if ($structure !== null) {
				$blocks[] = $structure;
			}
		}

		if (count($blocks) < 2) {
			return $blocks === [] ? null : [...$blocks[0], 'layout' => $layout->array()];
		}

		$structure = [
			'uid' => is_string($row['uid'] ?? null) ? $row['uid'] : null,
			'layout' => $layout->array(),
			'blocks' => $blocks,
		];

		if (is_array($row['meta'] ?? null) && $row['meta'] !== []) {
			$structure['meta'] = $row['meta'];
		}

		return $structure;
	}

	/**
	 * One row: uid, a layout (out-of-range values are rejected, not
	 * clamped, so a programmatic write fails loudly; the bounds that
	 * depend on the value's column count are checked against it; a
	 * position is optional, but both of its lines or neither), the
	 * block meta, and either an allowed type with its fields or — at the
	 * top level only — the `blocks` of a split, whose blocks have no
	 * position of their own.
	 */
	private function rowsShape(bool $children = false): Shape
	{
		$layout = Shapes::create();
		$layout->add('colspan', 'int')->rules('required', 'min:1', 'max:' . self::MAX_COLUMNS);
		$layout->add('rowspan', 'int')->rules('required', 'min:1', 'max:' . Layout::MAX_ROWSPAN);

		// Without a position a block is placed below the others on read.
		if (!$children) {
			$layout->add('col', 'int')->optional()->rules('min:1', 'max:' . self::MAX_COLUMNS);
			$layout->add('row', 'int')->optional()->rules('min:1');
		}

		$rows = Shapes::list();
		$rows->add('uid', 'string')->rules('required');
		$type = $rows->add('type', 'string')->rules('in:' . implode(',', $this->allowedBlockTypes()));
		$rows->add('layout', $layout)->rules('required');
		$fields = $rows->add('fields', Shapes::create())->finalize($this->finalizeRowFields(...));
		$meta = Shapes::create()->extra(Extra::Allow);
		$meta->add('padding', $this->spacingShape())->optional()->nullable();
		$rows->add('meta', $meta)->optional()->nullable();

		if ($children) {
			$type->rules('required');
			$fields->rules('required');
			$rows->review($this->reviewChildren(...));

			return $rows;
		}

		$type->optional();
		$fields->optional();
		$rows->add('blocks', $this->rowsShape(children: true))->optional();
		$rows->review($this->reviewRows(...));

		return $rows;
	}

	private function reviewRows(Review $review): void
	{
		$this->reviewRowFields($review);

		foreach ($review->values() as $index => $row) {
			// An optional type may arrive empty; a non-empty one is allowed.
			$blocks = $row['blocks'] ?? null;
			$isBlock = ($row['type'] ?? '') !== '' && isset($row['fields']) && $blocks === null;
			$isSplit =
				!array_key_exists('type', $row)
				&& !array_key_exists('fields', $row)
				&& is_array($blocks)
				&& count($blocks) >= 2;

			if (!$isSplit && !$isBlock) {
				$review->addError([$index], __('block:row-kind'));
			}
		}
	}

	private function reviewChildren(Review $review): void
	{
		$this->reviewRowFields($review);

		foreach ($review->values() as $index => $row) {
			if (array_key_exists('blocks', $row)) {
				$review->addError([$index, 'blocks'], __('block:row-kind'));
			}
		}
	}

	/**
	 * A split's area has no rows beyond its own — a subgrid never grows —
	 * so a block the flow would put outside it lands on top of another.
	 * Its blocks are placed as the browser's sparse row flow places them.
	 *
	 * @param list<int|string> $path
	 * @param list<mixed> $blocks
	 */
	private function reviewSplit(Review $review, array $path, Layout $area, array $blocks, int $min): void
	{
		$spans = [];

		foreach ($blocks as $position => $block) {
			$layout = is_array($block) && is_array($block['layout'] ?? null) ? $block['layout'] : [];
			$colspan = (int) ($layout['colspan'] ?? 0);

			if ($colspan < $min || $colspan > $area->colspan) {
				$review->addError([...$path, $position, 'layout', 'colspan'], __('block:invalid-width'));

				return;
			}

			$spans[] = ['colspan' => $colspan, 'rowspan' => (int) ($layout['rowspan'] ?? 1)];
		}

		foreach (Placement::flow($spans, $area->colspan) as $position => $spot) {
			if (($spot['row'] + $spans[$position]['rowspan'] - 1) > $area->rowspan) {
				$review->addError([...$path, $position, 'layout'], __('block:children-overflow'));

				return;
			}
		}
	}
}
