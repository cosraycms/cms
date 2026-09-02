<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Option;
use Cosray\Schema\Label;
use Cosray\Schema\Options;

#[Label('Test Option Entry')]
final class TestOptionEntry
{
	#[Label('Size')]
	#[Options(['small', 'large'])]
	private Option $size;
}
