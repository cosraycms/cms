<?php

declare(strict_types=1);

namespace Cosray\References;

use Cosray\Field;
use Cosray\Richtext;

/**
 * Collects every asset and node uid referenced by stored node content:
 * media field items (`{uid}`), Reference field targets, and the richtext
 * carriers (`image.uid`, `link.asset`, `link.node`). The rows of Blocks
 * and Entries fields are recursed into. Feeds the reference indexes.
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
			$this->fields($content, $assets, $nodes);
			$richtext = Richtext\Scanner::scanContent($content);
			$assets = [...$assets, ...$richtext['assets']];
			$nodes = [...$nodes, ...$richtext['nodes']];
		}

		return ['assets' => $this->unique($assets), 'nodes' => $this->unique($nodes)];
	}

	/**
	 * @param list<string> $assets
	 * @param list<string> $nodes
	 */
	private function fields(array $content, array &$assets, array &$nodes): void
	{
		foreach ($content as $field) {
			if (!is_array($field) || !is_string($field['type'] ?? null)) {
				continue;
			}

			$type = $this->index->resolve($field['type']);

			if ($type === null) {
				continue;
			}

			if (is_a($type, Field\Blocks::class, true) || is_a($type, Field\Entries::class, true)) {
				$this->rows($field['value'] ?? null, $assets, $nodes);
			} elseif (is_a($type, Field\Reference::class, true)) {
				$this->collect($field['value'] ?? null, $nodes);
			} elseif (is_a($type, Field\File::class, true)) {
				$this->collect($field['value'] ?? null, $assets);
			}
		}
	}

	/**
	 * Typed rows carry their sub-fields under `fields`, per locale list.
	 *
	 * @param list<string> $assets
	 * @param list<string> $nodes
	 */
	private function rows(mixed $value, array &$assets, array &$nodes): void
	{
		foreach (is_array($value) ? $value : [] as $rows) {
			foreach (is_array($rows) ? $rows : [] as $row) {
				if (!is_array($row) || !is_array($row['fields'] ?? null)) {
					continue;
				}

				$this->fields($row['fields'], $assets, $nodes);
			}
		}
	}

	/**
	 * Collect uids from a locale-keyed map of `{uid}` lists into $out.
	 *
	 * @param list<string> $out
	 */
	private function collect(mixed $value, array &$out): void
	{
		foreach (is_array($value) ? $value : [] as $items) {
			$this->items($items, $out);
		}
	}

	/** @param list<string> $out */
	private function items(mixed $items, array &$out): void
	{
		foreach (is_array($items) ? $items : [] as $item) {
			$uid = is_array($item) ? $item['uid'] ?? null : null;

			if (is_string($uid) && $uid !== '') {
				$out[] = $uid;
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
