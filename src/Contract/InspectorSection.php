<?php

declare(strict_types=1);

namespace Cosray\Contract;

use Cosray\Node\Wrapper;
use Cosray\Panel\Inspector\Section;

/**
 * A section of its own in the status tab of the editor's inspector, below
 * the node's status. The node is the stored one as the editor shows it,
 * with its working copy. Null leaves the section out: decide it by what
 * stays the same while the editor is open, like the node's type or the
 * user's permissions, as the section is drawn again after every save and
 * switch but never removed.
 */
interface InspectorSection
{
	public function section(Wrapper $node): ?Section;
}
