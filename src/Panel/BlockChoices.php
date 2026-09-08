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
			throw new RuntimeException('Invalid block type descriptors');
		}
		$types = [];
		foreach ($descriptors as $type) {
			if (!is_array($type) || !is_string($type['type'] ?? null) || $type['type'] === '') {
				throw new RuntimeException('Invalid block type descriptor');
			}
			$types[$type['type']] = $type;
		}
		$this->types = $types;
		$common = array_key_exists('commonTypes', $props) ? $props['commonTypes'] : [];
		if (!is_array($common) || !array_is_list($common)) {
			throw new RuntimeException('Invalid common block type selection');
		}
		foreach ($common as $type) {
			if (!is_string($type)) {
				throw new RuntimeException('Invalid common block type selection');
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
}
