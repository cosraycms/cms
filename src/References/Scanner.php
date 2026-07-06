<?php

declare(strict_types=1);

namespace Cosray\References;

use Cosray\Field;
use Cosray\Richtext;

/**
 * Collects every asset and node uid referenced by stored node content:
 * media field items (`{uid}`), image/images/video block items, and the
 * richtext carriers (`image.uid`, `link.asset`, `link.node`). Blocks
 * and Entries are recursed into. Feeds the reference indexes.
 */
final class Scanner
{
	private readonly Field\Index $index;

	public function __construct(?Field\Index $index = null)
	{
		$this->index = $index ?? Field\Index::withDefaults();
	}

	/** @return array{assets: list<string>, nodes: list<string>} */
	public function scan(mixed $content): array
	{
		$assets = [];
		$nodes = [];

		if (is_array($content)) {
			$this->fields($content, $assets);
			$richtext = Richtext\Scanner::scanContent($content);
			$assets = [...$assets, ...$richtext['assets']];
			$nodes = $richtext['nodes'];
		}

		return ['assets' => $this->unique($assets), 'nodes' => $this->unique($nodes)];
	}

	/** @param list<string> $assets */
	private function fields(array $content, array &$assets): void
	{
		foreach ($content as $field) {
			if (!is_array($field) || !is_string($field['type'] ?? null)) {
				continue;
			}

			$type = $this->index->resolve($field['type']);

			if ($type === null) {
				continue;
			}

			if (is_a($type, Field\Blocks::class, true)) {
				$this->blocks($field['value'] ?? null, $assets);
			} elseif (is_a($type, Field\Entries::class, true)) {
				$this->entries($field['value'] ?? null, $assets);
			} elseif (is_a($type, Field\File::class, true)) {
				$this->localizedItems($field['value'] ?? null, $assets);
			}
		}
	}

	/** @param list<string> $assets */
	private function blocks(mixed $value, array &$assets): void
	{
		foreach (is_array($value) ? $value : [] as $blocks) {
			foreach (is_array($blocks) ? $blocks : [] as $block) {
				if (!is_array($block)) {
					continue;
				}

				// Richtext blocks carry the format envelope and are
				// covered by the richtext scan.
				if (in_array($block['type'] ?? null, ['image', 'images', 'video'], true)) {
					$this->items($block['value'] ?? null, $assets);
				}
			}
		}
	}

	/** @param list<string> $assets */
	private function entries(mixed $value, array &$assets): void
	{
		foreach (is_array($value) ? $value : [] as $entries) {
			foreach (is_array($entries) ? $entries : [] as $entry) {
				if (!is_array($entry) || !is_array($entry['fields'] ?? null)) {
					continue;
				}

				$this->fields($entry['fields'], $assets);
			}
		}
	}

	/** @param list<string> $assets */
	private function localizedItems(mixed $value, array &$assets): void
	{
		foreach (is_array($value) ? $value : [] as $items) {
			$this->items($items, $assets);
		}
	}

	/** @param list<string> $assets */
	private function items(mixed $items, array &$assets): void
	{
		foreach (is_array($items) ? $items : [] as $item) {
			$uid = is_array($item) ? $item['uid'] ?? null : null;

			if (is_string($uid) && $uid !== '') {
				$assets[] = $uid;
			}
		}
	}

	/**
	 * @param list<string> $uids
	 * @return list<string>
	 */
	private function unique(array $uids): array
	{
		$uids = array_values(array_unique($uids));
		sort($uids);

		return $uids;
	}
}
