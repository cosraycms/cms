<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;
use Cosray\Schema\FieldOrder;
use Cosray\Schema\Fieldset;

#[FieldOrder('title', 'after', 'body', 'before')]
final class TestSplitFieldsetDocument
{
	private Text $before;

	#[Fieldset]
	private TestBaseFields $baseFields;

	private Text $after;
}
