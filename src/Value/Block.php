<?php

declare(strict_types=1);

namespace Cosray\Value;

use Closure;
use Cosray\Block\Layout;
use Cosray\Block\RenderContext;
use Cosray\Contract\Block as BlockType;
use Cosray\Exception\NoSuchProperty;
use Cosray\Field;

use function Cosray\escape;

/**
 * One row of a Blocks field: its type, grid placement, block meta and
 * the type's fields, reachable as properties like on an entry. A split
 * has no type and no fields; it holds blocks laid out on its own area.
 *
 * @property-read Field\Blocks $field
 */
class Block extends Value
{
	/** @var array<string, Field\Field> */
	protected array $fields = [];

	/** @var list<Block> */
	protected array $blocks = [];

	protected readonly Layout $layout;

	/**
	 * @param ?string $type null for a split
	 * @param ?Layout $area the split a block sits in, which bounds its layout
	 */
	public function __construct(
		Field\Owner $owner,
		Field\Blocks $field,
		ValueContext $context,
		public readonly ?string $type,
		?Layout $area = null,
	) {
		parent::__construct($owner, $field, $context);

		$this->layout = $area === null
			? Layout::normalize($this->data['layout'] ?? null, $field->getColumns(), $field->getMin())
			: Layout::normalize($this->data['layout'] ?? null, $area->colspan, $field->getMin(), $area->rowspan);

		if ($type !== null) {
			$data = $this->data['fields'] ?? [];
			$this->fields = $field->blockFieldsFor($type, is_array($data) ? $data : []);

			return;
		}

		foreach (is_array($this->data['blocks'] ?? null) ? $this->data['blocks'] : [] as $row) {
			$childType = is_array($row) ? $row['type'] ?? null : null;

			if (is_string($childType) && $field->allows($childType)) {
				$this->blocks[] = new self(
					$owner,
					$field,
					new ValueContext($this->fieldName, $row),
					$childType,
					$this->layout,
				);
			}
		}
	}

	public function __toString(): string
	{
		return $this->render();
	}

	public function uid(): ?string
	{
		$uid = $this->data['uid'] ?? null;

		return is_string($uid) ? $uid : null;
	}

	/** The type's `data-type` value; a split has none. */
	public function handle(): ?string
	{
		return $this->type === null ? null : $this->field->blockHandle($this->type);
	}

	public function layout(): Layout
	{
		return $this->layout;
	}

	public function isSplit(): bool
	{
		return $this->type === null;
	}

	/** @return list<Block> a split's blocks in reading order, empty for a block */
	public function blocks(): array
	{
		return $this->blocks;
	}

	/**
	 * `columns` when a split's blocks sit side by side, `rows` when they
	 * are stacked, read off the blocks since the direction is not stored.
	 */
	public function split(): ?string
	{
		if ($this->blocks === []) {
			return null;
		}

		return $this->blocks[0]->layout->colspan < $this->layout->colspan ? 'columns' : 'rows';
	}

	/** A block-level meta entry, such as `class` or `id`. */
	public function meta(string $key, mixed $default = null): mixed
	{
		return parent::meta($key, $default);
	}

	/** The block's padding as a spacing token, `null` for the site's default. */
	public function padding(): ?string
	{
		$value = $this->meta('padding');

		return is_string($value) && in_array($value, Field\Blocks::SPACING, true) ? $value : null;
	}

	public function json(): array
	{
		return $this->resolve(static fn(Value $value): mixed => $value->json());
	}

	public function unwrap(): array
	{
		return $this->resolve(static fn(Value $value): mixed => $value->unwrap());
	}

	public function isset(): bool
	{
		return $this->type === null ? $this->blocks !== [] : count($this->fields) > 0;
	}

	public function __get(string $name): mixed
	{
		if (isset($this->fields[$name])) {
			return $this->fields[$name]->value();
		}

		throw new NoSuchProperty("Block doesn't have field '{$name}'");
	}

	/**
	 * This block alone, wrapper element included. Supports the same
	 * arguments as `Blocks::render()`.
	 */
	public function render(mixed ...$args): string
	{
		$ctx = new RenderContext($this->owner, $this->fieldName, $this->field->getColumns(), $args);

		return $this->renderWith($ctx, $this->field->services()->blocks->cached($this->owner));
	}

	/**
	 * The rendering contract: `{prefix}-block` plus the meta class, the
	 * meta id, `data-type` — or `data-split` for a split, whose blocks
	 * follow inside —, the layout as data attributes and custom
	 * properties, then the type's output. An empty output emits no
	 * element, so a block whose asset is gone leaves no grid cell.
	 *
	 * @param Closure(class-string<BlockType>): BlockType $types
	 */
	public function renderWith(RenderContext $ctx, Closure $types): string
	{
		if ($this->type === null) {
			$inner = '';

			foreach ($this->blocks as $block) {
				$inner .= $block->renderWith($ctx, $types);
			}
		} else {
			$inner = $types($this->type)->render($this, $ctx);
		}

		if ($inner === '') {
			return '';
		}

		$class = $ctx->prefix() . '-block';
		$styleClass = $this->styleClass();

		if ($styleClass !== null) {
			$class .= ' ' . $styleClass;
		}

		$attributes = ' class="' . escape($class) . '"';
		$id = $this->elementId();

		if ($id !== null) {
			$attributes .= ' id="' . escape($id) . '"';
		}

		$attributes .= $this->type === null
			? ' data-split="' . escape((string) $this->split()) . '"'
			: ' data-type="' . escape((string) $this->handle()) . '"';
		$layout = $this->layout;
		// The columns the block takes out of its row: the reference sheet
		// spans them and pushes the box past the indent. Derived, but
		// emitted so CSS that cannot read the inline style still has it.
		$reserved = $layout->indent + $layout->colspan;
		$attributes .=
			" data-colspan=\"{$layout->colspan}\" data-rowspan=\"{$layout->rowspan}\" data-indent=\"{$layout->indent}\""
			. " data-reserved=\"{$reserved}\""
			. ($this->padding() !== null ? ' data-padding="' . escape((string) $this->padding()) . '"' : '')
			. " style=\"--colspan: {$layout->colspan}; --rowspan: {$layout->rowspan}; --indent: {$layout->indent};"
			. " --reserved: {$reserved}\"";

		return "<div{$attributes}>{$inner}</div>";
	}

	/**
	 * @param callable(Value): mixed $resolve
	 * @return array{uid: ?string, type: ?string, handle: ?string, layout: array, fields?: array<string, mixed>, blocks?: list<array>, meta: array}
	 */
	private function resolve(callable $resolve): array
	{
		$meta = $this->data['meta'] ?? [];
		$result = [
			'uid' => $this->uid(),
			'type' => $this->type,
			'handle' => $this->handle(),
			'layout' => $this->layout->array(),
		];

		if ($this->type === null) {
			$result['blocks'] = array_map(static fn(Block $block): array => $block->resolve($resolve), $this->blocks);
		} else {
			$result['fields'] = array_map(static fn(Field\Field $field): mixed => $resolve(
				$field->value(),
			), $this->fields);
		}

		$result['meta'] = is_array($meta) ? $meta : [];

		return $result;
	}
}
