<?php

declare(strict_types=1);

namespace Cosray\Contract;

use Cosray\Finder\Nodes;

/**
 * Narrows what a collection lists beyond its #[Types]. The finder covers
 * nodes in any state and is already limited to the collection's #[Types]
 * when it declares them. Ordering set here is replaced by the listing's
 * column sorts.
 */
interface Entries
{
	public function entries(Nodes $nodes): Nodes;
}
