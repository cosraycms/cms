<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Blocks;
use Cosray\Schema\Label;

#[Label('Test Blocks Entry')]
final class TestBlocksEntry
{
	#[Label('Body')]
	private Blocks $body;
}
