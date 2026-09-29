<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Quma\Database;
use Cosray\Exception\RuntimeException;

/**
 * Writes manual node orders. A scope is either the children of one parent
 * or the top level of one collection, and every scope keeps its own order,
 * so a node can hold a position in several. Positions are structure, not
 * content: they bypass content versions, history and working copies and
 * take effect immediately.
 *
 * Callers pass the complete order of a scope, which is renumbered 1..n.
 * Rows of members missing from it are dropped, so members without a
 * position sort after the positioned ones.
 *
 * @api
 */
final class Positions
{
	public function __construct(
		private readonly Database $db,
	) {}

	/** @param list<string> $uids the parent's children in their new order */
	public function orderChildren(string $parent, array $uids): void
	{
		$this->db->positions->orderChildren([
			'parent' => $parent,
			'uids' => json_encode(array_values($uids), JSON_THROW_ON_ERROR),
		])->run();
	}

	/** @param list<string> $uids the collection's top-level entries in their new order */
	public function orderEntries(string $collection, array $uids): void
	{
		$this->db->positions->orderEntries([
			'collection' => $collection,
			'uids' => json_encode(array_values($uids), JSON_THROW_ON_ERROR),
		])->run();
	}

	/**
	 * The group with `$uid` moved directly before or after `$neighbour`.
	 * A neighbour rather than an index, because a listing may show only
	 * part of a group (a page, or the types a collection lists), where an
	 * index would be ambiguous.
	 *
	 * @param list<string> $group a scope's members in their current order
	 * @return list<string>
	 */
	public static function move(array $group, string $uid, string $neighbour, bool $after): array
	{
		if ($uid === $neighbour) {
			throw new RuntimeException('A node cannot be placed next to itself');
		}

		if (!in_array($uid, $group, true) || !in_array($neighbour, $group, true)) {
			throw new RuntimeException('The node and its neighbour must belong to the same group');
		}

		$rest = array_values(array_filter($group, static fn(string $member): bool => $member !== $uid));
		$index = (int) array_search($neighbour, $rest, true) + ($after ? 1 : 0);
		array_splice($rest, $index, 0, [$uid]);

		return $rest;
	}
}
