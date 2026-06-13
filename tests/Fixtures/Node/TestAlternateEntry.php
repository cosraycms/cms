<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Required;

#[Label('Test Alternate Entry')]
class TestAlternateEntry
{
	#[Label('Name'), Required]
	protected Text $name;
}
