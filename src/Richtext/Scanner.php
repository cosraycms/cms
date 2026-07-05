<?php

declare(strict_types=1);

namespace Cosray\Richtext;

/**
 * Collects the complete set of reference carriers from a richtext
 * document: `image.uid`, `link.asset`, and `link.node`. Feeds the
 * reference indexes (Phase 2) and the panel's asset lookup.
 */
final class Scanner
{
	/** @return array{assets: list<string>, nodes: list<string>} */
	public static function scan(mixed $doc): array
	{
		$assets = [];
		$nodes = [];
		self::walk(is_array($doc) ? $doc : [], $assets, $nodes);

		return [
			'assets' => array_values(array_unique($assets)),
			'nodes' => array_values(array_unique($nodes)),
		];
	}

	/**
	 * @param list<string> $assets
	 * @param list<string> $nodes
	 */
	private static function walk(array $node, array &$assets, array &$nodes): void
	{
		if (($node['type'] ?? null) === 'image') {
			$uid = $node['attrs']['uid'] ?? null;

			if (is_string($uid) && $uid !== '') {
				$assets[] = $uid;
			}
		}

		foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $mark) {
			if (!is_array($mark) || ($mark['type'] ?? null) !== 'link') {
				continue;
			}

			$asset = $mark['attrs']['asset'] ?? null;
			$target = $mark['attrs']['node'] ?? null;

			if (is_string($asset) && $asset !== '') {
				$assets[] = $asset;
			}

			if (is_string($target) && $target !== '') {
				$nodes[] = $target;
			}
		}

		foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
			if (is_array($child)) {
				self::walk($child, $assets, $nodes);
			}
		}
	}
}
