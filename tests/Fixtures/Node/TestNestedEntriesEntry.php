<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Entries;
use Cosray\Field\Text;
use Cosray\Schema\Allows;
use Cosray\Schema\Label;

#[Label('Nested Entries Entry')]
class TestNestedEntriesEntry
{
	#[Label('Title')]
	protected Text $title;

	#[Label('Nested'), Allows(TestEntry::class)]
	protected Entries $nested;
}
