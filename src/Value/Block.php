<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Block\Layout;
use Cosray\Block\RenderContext;
use Cosray\Contract\Block as BlockType;
use Cosray\Exception\NoSuchProperty;
use Cosray\Field;

use function Cosray\escape;

/**
 * One row of a Blocks field: its type, grid placement, block meta and
 * the type's fields, reachable as properties like on an entry.
 *
 * @property-read Field\Blocks $field
 */
class Block extends Value
{
	/** @var array<string, Field\Field> */
	protected array $fields = [];

	protected readonly Layout $layout;

	public function __construct(
		Field\Owner $owner,
		Field\Blocks $field,
		ValueContext $context,
		public readonly string $type,
	) {
		parent::__construct($owner, $field, $context);

		$data = $this->data['fields'] ?? [];
		$this->fields = $field->blockFieldsFor($type, is_array($data) ? $data : []);
		$this->layout = Layout::normalize($this->data['layout'] ?? null, $field->getColumns(), $field->getMin());
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

	/** The type's `data-type` value. */
	public function handle(): string
	{
		return $this->field->blockHandle($this->type);
	}

	public function layout(): Layout
	{
		return $this->layout;
	}

	/** A block-level meta entry, such as `class` or `id`. */
	public function meta(string $key, mixed $default = null): mixed
	{
		return parent::meta($key, $default);
	}

	public function json(): array
	{
		return $this->fieldValues(static fn(Value $value): mixed => $value->json());
	}

	public function unwrap(): array
	{
		return $this->fieldValues(static fn(Value $value): mixed => $value->unwrap());
	}

	public function isset(): bool
	{
		return count($this->fields) > 0;
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

		return $this->renderWith($ctx, $this->field->services()->blocks->create($this->type, $this->owner));
	}

	/**
	 * The rendering contract: `{prefix}-block` plus the meta class, the
	 * meta id, `data-type`, the layout as data attributes and custom
	 * properties, then the type's output. An empty output emits no
	 * element, so a block whose asset is gone leaves no grid cell.
	 */
	public function renderWith(RenderContext $ctx, BlockType $type): string
	{
		$inner = $type->render($this, $ctx);

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

		$layout = $this->layout;
		// The columns the block takes out of its row: the reference sheet
		// spans them and pushes the box past the indent. Derived, but
		// emitted so CSS that cannot read the inline style still has it.
		$reserved = $layout->indent + $layout->span;
		$attributes .=
			' data-type="'
			. escape($this->handle())
			. "\" data-span=\"{$layout->span}\" data-rows=\"{$layout->rows}\" data-indent=\"{$layout->indent}\""
			. " data-reserved=\"{$reserved}\""
			. " style=\"--span: {$layout->span}; --rows: {$layout->rows}; --indent: {$layout->indent};"
			. " --reserved: {$reserved}\"";

		return "<div{$attributes}>{$inner}</div>";
	}

	/**
	 * @param callable(Value): mixed $resolve
	 * @return array{uid: ?string, type: string, handle: string, layout: array, fields: array<string, mixed>, meta: array}
	 */
	private function fieldValues(callable $resolve): array
	{
		$fields = [];

		foreach ($this->fields as $name => $field) {
			$fields[$name] = $resolve($field->value());
		}

		$meta = $this->data['meta'] ?? [];

		return [
			'uid' => $this->uid(),
			'type' => $this->type,
			'handle' => $this->handle(),
			'layout' => $this->layout->array(),
			'fields' => $fields,
			'meta' => is_array($meta) ? $meta : [],
		];
	}
}
