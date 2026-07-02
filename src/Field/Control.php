<?php

declare(strict_types=1);

namespace Cosray\Field;

/**
 * Declarative editor control descriptor.
 *
 * Every field type describes its editor UI as a named control from a
 * fixed vocabulary interpreted by the panel's generic renderer. The
 * `element` control loads a custom element (web component) shipped by
 * a plugin — the escape hatch for UIs the vocabulary cannot express.
 *
 * Cross-cutting concerns (label, locale tabs, required marker,
 * description, width) stay outside the descriptor; they come from the
 * field's properties() and are rendered by the shared field wrapper.
 */
final class Control
{
	private function __construct(
		public readonly string $name,
		private readonly array $props = [],
	) {}

	public static function text(?string $placeholder = null): self
	{
		return new self('text', $placeholder === null ? [] : ['placeholder' => $placeholder]);
	}

	public static function textarea(): self
	{
		return new self('textarea');
	}

	public static function number(
		int|float|string|null $step = null,
		int|float|null $min = null,
		int|float|null $max = null,
	): self {
		return new self('number', array_filter(
			['step' => $step, 'min' => $min, 'max' => $max],
			static fn($value) => $value !== null,
		));
	}

	public static function checkbox(): self
	{
		return new self('checkbox');
	}

	public static function option(string $display = 'select'): self
	{
		return new self('option', ['display' => $display]);
	}

	public static function date(): self
	{
		return new self('date');
	}

	public static function time(): self
	{
		return new self('time');
	}

	public static function datetime(): self
	{
		return new self('datetime');
	}

	public static function hidden(): self
	{
		return new self('hidden');
	}

	public static function code(): self
	{
		return new self('code');
	}

	public static function richtext(): self
	{
		return new self('richtext');
	}

	public static function image(): self
	{
		return new self('image');
	}

	public static function file(): self
	{
		return new self('file');
	}

	public static function video(): self
	{
		return new self('video');
	}

	public static function iframe(): self
	{
		return new self('iframe');
	}

	public static function blocks(): self
	{
		return new self('blocks');
	}

	public static function entries(): self
	{
		return new self('entries');
	}

	/** @param list<array{key: string, label?: string, control: self}> $fields */
	public static function group(array $fields): self
	{
		return new self('group', ['fields' => $fields]);
	}

	public static function repeater(self $item, ?int $min = null, ?int $max = null): self
	{
		return new self('repeater', array_filter(
			['item' => $item, 'min' => $min, 'max' => $max],
			static fn($value) => $value !== null,
		));
	}

	/**
	 * Custom element escape hatch.
	 *
	 * @param string $tag custom element tag, e.g. 'acme-color-picker'
	 * @param string $module module path '{pluginId}/{file}.js', served
	 *                       from the plugin's asset dir
	 */
	public static function element(string $tag, string $module): self
	{
		return new self('element', ['tag' => $tag, 'module' => $module]);
	}

	public function prop(string $key, mixed $value): self
	{
		return new self($this->name, [...$this->props, $key => $value]);
	}

	public function array(): array
	{
		return [
			'name' => $this->name,
			'props' => self::serialize($this->props),
		];
	}

	private static function serialize(array $values): array
	{
		return array_map(
			static fn($value) => match (true) {
				$value instanceof self => $value->array(),
				is_array($value) => self::serialize($value),
				default => $value,
			},
			$values,
		);
	}
}
