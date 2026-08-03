<?php

declare(strict_types=1);

namespace Cosray\Contract;

use Cosray\Node\Wrapper;

interface ViewContext
{
	/**
	 * Extra template variables for this node's view.
	 *
	 * Runs whenever the node is rendered — at its own url path and when it
	 * is embedded through `$cms->render()`. The wrapper is the same object
	 * the template receives, so view preparation can move here unchanged.
	 *
	 * Returned keys are merged over the base context (`node`, `cms`,
	 * `locale`, `request`, ...), so reusing one of those names replaces it.
	 *
	 * @return array<string, mixed>
	 */
	public function viewContext(Wrapper $node): array;
}
