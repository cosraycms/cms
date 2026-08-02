<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;

final class NodeWithTitleAndAmbiguousEmbeds implements Title
{
	private TestBaseFields $baseFields;
	private TestAlternateTitleFields $alternateFields;

	public function title(): string
	{
		return 'outer';
	}
}
