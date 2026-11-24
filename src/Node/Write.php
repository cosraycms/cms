<?php

declare(strict_types=1);

namespace Cosray\Node;

use Cosray\Actor;
use DateTimeImmutable;

/**
 * One write to a node, as `Contract\NodeListener` hears of it: what it did,
 * in the names its events carry, such as `applied` and `published`.
 */
final readonly class Write
{
	/**
	 * @param string $type the handle of the node's type
	 * @param DateTimeImmutable $at the time the write's events share
	 * @param list<string> $actions
	 * @param array<string, array<string, mixed>> $data what an action recorded besides, by action
	 */
	public function __construct(
		public string $uid,
		public string $type,
		public Actor $actor,
		public DateTimeImmutable $at,
		public array $actions,
		private array $data = [],
	) {}

	/** Whether the write did one of the actions. */
	public function has(string ...$actions): bool
	{
		return array_intersect($actions, $this->actions) !== [];
	}

	/**
	 * What the action recorded besides, like the parents of a move or the
	 * cause its caller gave.
	 *
	 * @return array<string, mixed>
	 */
	public function data(string $action): array
	{
		return $this->data[$action] ?? [];
	}
}
