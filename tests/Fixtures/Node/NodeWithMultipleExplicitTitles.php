<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;
use Cosray\Schema\Title;

#[Title('heading')]
final class NodeWithMultipleExplicitTitles
{
	private Text $heading;

	#[Title]
	private TestBaseFields $baseFields;
}
