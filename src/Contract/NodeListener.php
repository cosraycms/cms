<?php

declare(strict_types=1);

namespace Cosray\Contract;

use Cosray\Node\Write;

/**
 * Hears of every write `Cosray\Node\Store` records, through the panel, the
 * API or PHP code alike.
 *
 * It runs inside the write's transaction, after its events are recorded:
 * what it writes or queues is committed or rolled back with the write, and
 * an exception fails the write. Slow work, like a call to another system,
 * belongs in a job it pushes. A write that changes nothing reaches no
 * listener.
 */
interface NodeListener
{
	public function written(Write $write): void;
}
