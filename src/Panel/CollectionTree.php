<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Traversable;

final class CollectionTree
{
	/**
	 * @param list<array<string, mixed>> $nodes
	 * @param list<string> $open
	 * @param callable(string): list<array<string, mixed>> $children
	 * @return list<array{node: array<string, mixed>, depth: int, expanded: bool, last: bool, descendants: list<string>}>
	 */
	public static function build(array $nodes, array $open, callable $children): array
	{
		[$rows] = self::walk(
			nodes: $nodes,
			open: array_fill_keys($open, true),
			children: $children,
			depth: 0,
			stack: [],
		);

		return $rows;
	}

	/**
	 * @param list<array<string, mixed>> $nodes
	 * @param array<string, true> $open
	 * @param callable(string): list<array<string, mixed>> $children
	 * @param list<string> $stack
	 * @return array{0: list<array{node: array<string, mixed>, depth: int, expanded: bool, last: bool, descendants: list<string>}>, 1: list<string>}
	 */
	private static function walk(
		array $nodes,
		array $open,
		callable $children,
		int $depth,
		array $stack,
	): array {
		$rows = [];
		$uids = [];

		$lastIndex = count($nodes) - 1;
		$position = 0;

		foreach ($nodes as $node) {
			$node = self::arrayFrom($node);
			$uid = trim((string) ($node['uid'] ?? ''));
			$expanded =
				$uid !== ''
				&& isset($open[$uid])
				&& (bool) ($node['hasChildren'] ?? false)
				&& !in_array($uid, $stack, true);
			$childRows = [];
			$descendants = [];

			if ($expanded) {
				[$childRows, $descendants] = self::walk(
					nodes: $children($uid),
					open: $open,
					children: $children,
					depth: $depth + 1,
					stack: array_merge($stack, [$uid]),
				);
			}

			$rows[] = [
				'node' => $node,
				'depth' => $depth,
				'expanded' => $expanded,
				'last' => $position === $lastIndex,
				'descendants' => $descendants,
			];

			$position++;

			if ($uid !== '') {
				$uids[] = $uid;
			}

			array_push($rows, ...$childRows);
			array_push($uids, ...$descendants);
		}

		return [$rows, array_values(array_unique($uids))];
	}

	/** @return array<string, mixed> */
	private static function arrayFrom(mixed $value): array
	{
		if ($value instanceof Traversable) {
			return iterator_to_array($value);
		}

		return is_array($value) ? $value : [];
	}
}
