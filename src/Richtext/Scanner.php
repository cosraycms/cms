<?php

declare(strict_types=1);

namespace Cosray\Richtext;

/**
 * Collects the complete set of reference carriers from a richtext
 * document: `image.uid`, `link.asset`, and `link.node`, and from a whole
 * content array also the link of an image's use. Feeds the reference
 * indexes and the panel's asset lookup.
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
	 * Scan a whole node content array: every envelope-marked richtext
	 * value (fields and blocks alike) contributes its references.
	 *
	 * @return array{assets: list<string>, nodes: list<string>}
	 */
	public static function scanContent(mixed $content): array
	{
		$assets = [];
		$nodes = [];
		self::walkContent(is_array($content) ? $content : [], $assets, $nodes);

		return [
			'assets' => array_values(array_unique($assets)),
			'nodes' => array_values(array_unique($nodes)),
		];
	}

	/**
	 * @param array<mixed> $data
	 * @param list<string> $assets
	 * @param list<string> $nodes
	 */
	private static function walkContent(array $data, array &$assets, array &$nodes): void
	{
		if (($data['format'] ?? null) === Envelope::FORMAT && is_array($data['value'] ?? null)) {
			foreach ($data['value'] as $doc) {
				if (is_array($doc)) {
					self::walk($doc, $assets, $nodes);
				}
			}

			return;
		}

		if (is_array($data['meta']['link'] ?? null)) {
			self::link($data['meta']['link'], $assets, $nodes);
		}

		if (is_string($data['meta']['poster'] ?? null) && $data['meta']['poster'] !== '') {
			$assets[] = $data['meta']['poster'];
		}

		foreach ($data as $value) {
			if (is_array($value)) {
				self::walkContent($value, $assets, $nodes);
			}
		}
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
				self::link($mark['attrs'], $assets, $nodes);
			}
		}

		foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
			if (is_array($child)) {
				self::walk($child, $assets, $nodes);
			}
		}
	}

	/**
	 * @param array<mixed> $attrs
	 * @param list<string> $assets
	 * @param list<string> $nodes
	 */
	private static function link(array $attrs, array &$assets, array &$nodes): void
	{
		$asset = $attrs['asset'] ?? null;
		$target = $attrs['node'] ?? null;

		if (is_string($asset) && $asset !== '') {
			$assets[] = $asset;
		}

		if (is_string($target) && $target !== '') {
			$nodes[] = $target;
		}
	}
}
