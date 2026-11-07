<?php

declare(strict_types=1);

namespace Cosray\Richtext;

use Cosray\LinkTarget;

/**
 * Collects the reference carriers of a richtext document: `image.uid`,
 * `link.asset` and `link.node`.
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
	 * @param array<mixed> $node
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

			if (is_array($mark['attrs'] ?? null)) {
				$linked = LinkTarget::references($mark['attrs']);
				$assets = [...$assets, ...$linked['assets']];
				$nodes = [...$nodes, ...$linked['nodes']];
			}
		}

		foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
			if (is_array($child)) {
				self::walk($child, $assets, $nodes);
			}
		}
	}
}
