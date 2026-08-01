<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Schema\Title;

final class NodeWithInvalidEmbeddedTitle
{
	#[Title]
	private TestExplicitTitleFields $fields;
}
