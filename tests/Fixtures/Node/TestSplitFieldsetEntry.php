<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;
use Cosray\Schema\FieldOrder;
use Cosray\Schema\Fieldset;
use Cosray\Schema\Label;

#[Label('Test Split Fieldset Entry')]
#[FieldOrder('title', 'extra', 'body')]
final class TestSplitFieldsetEntry
{
	#[Fieldset]
	private TestBaseFields $baseFields;

	private Text $extra;
}
