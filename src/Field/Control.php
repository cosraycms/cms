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

	public static function blockText(): self
	{
		return new self('block-text');
	}

	public static function blockRichtext(): self
	{
		return new self('block-richtext');
	}

	public static function blockImage(): self
	{
		return new self('block-image');
	}

	public static function blockImages(): self
	{
		return new self('block-images');
	}

	public static function blockYoutube(): self
	{
		return new self('block-youtube');
	}

	public static function blockVideo(): self
	{
		return new self('block-video');
	}

	public static function blockIframe(): self
	{
		return new self('block-iframe');
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

	/**
	 * A named custom control registered via Registrar::control().
	 */
	public static function named(string $name): self
	{
		return new self($name);
	}

	public function prop(string $key, mixed $value): self
	{
		return new self($this->name, [...$this->props, $key => $value]);
	}

	/**
	 * Resolve named rich controls to their element form. Primitives,
	 * structural controls and unregistered names pass through; group
	 * and repeater resolve their nested descriptors.
	 */
	public function resolve(Control\Registry $controls): self
	{
		if ($this->name === 'group') {
			$fields = array_map(
				static fn(array $field): array => [
					...$field,
					'control' => $field['control']->resolve($controls),
				],
				$this->props['fields'] ?? [],
			);

			return new self('group', [...$this->props, 'fields' => $fields]);
		}

		if ($this->name === 'repeater') {
			return new self('repeater', [
				...$this->props,
				'item' => $this->props['item']->resolve($controls),
			]);
		}

		if ($this->name === 'element' || !$controls->has($this->name)) {
			return $this;
		}

		['tag' => $tag, 'module' => $module] = $controls->get($this->name);

		return new self('element', [...$this->props, 'tag' => $tag, 'module' => $module]);
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
