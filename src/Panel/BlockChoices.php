<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Cosray\Block;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Blocks;

final class BlockChoices
{
	public readonly array $types;
	public readonly array $all;
	public readonly array $common;

	public function __construct(string $field, array $props, ?callable $renderIcon = null)
	{
		$descriptors = $props['blockTypes'] ?? [];
		if (!is_array($descriptors)) {
			throw new RuntimeException(
				"Blocks field '{$field}' blockTypes must be a list of block type descriptors, "
					. self::describe($descriptors)
					. ' given',
			);
		}
		$types = [];
		foreach ($descriptors as $index => $type) {
			if (!is_array($type) || !is_string($type['type'] ?? null) || $type['type'] === '') {
				throw new RuntimeException(
					"Blocks field '{$field}' block type descriptor {$index} needs a non-empty string 'type', "
						. self::describe(is_array($type) ? $type['type'] ?? null : $type)
						. ' given',
				);
			}
			$types[$type['type']] = $type;
		}
		$this->types = $types;
		$common = array_key_exists('commonTypes', $props) ? $props['commonTypes'] : [];
		if (!is_array($common) || !array_is_list($common)) {
			throw new RuntimeException(
				"Blocks field '{$field}' commonTypes must be a list of block type IDs, "
					. self::describe($common)
					. ' given',
			);
		}
		foreach ($common as $index => $type) {
			if (!is_string($type)) {
				throw new RuntimeException(
					"Blocks field '{$field}' common type {$index} must be a block type ID, "
						. self::describe($type)
						. ' given',
				);
			}
		}
		$common = Blocks::commonSelection($common, array_keys($types), $field);

		$choices = [];
		foreach ($types as $id => $type) {
			$custom = $type['icon'] ?? null;
			$svg = is_array($custom) && $renderIcon !== null ? $renderIcon($custom) : '';
			$panelIcon = $custom !== null
				? 'square'
				: match ($id) {
					Block\RichText::class => 'body-text',
					Block\Text::class => 'text-left',
					Block\Heading::class => 'type-h1',
					Block\Image::class => 'image',
					Block\Images::class => 'images',
					Block\Video::class => 'film',
					Block\Youtube::class => 'play-btn',
					Block\Iframe::class => 'window',
					default => 'square',
				};
			$choices[$id] = [
				'type' => $id,
				'handle' => (string) ($type['handle'] ?? $id),
				'label' => (string) ($type['label'] ?? __('field:block')),
				// Debug providers can return only a diagnostic HTML comment.
				'icon' => preg_match('/<svg\b/i', $svg) === 1 ? $svg : Icon::render($panelIcon),
			];
		}
		$this->all = $choices;
		$this->common = array_map(static fn(string $type): array => $choices[$type], $common);
	}

	private static function describe(mixed $value): string
	{
		return match (true) {
			is_scalar($value) => var_export($value, true),
			is_array($value) && !array_is_list($value) => 'keyed array',
			default => get_debug_type($value),
		};
	}
}
