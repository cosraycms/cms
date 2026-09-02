<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\RichText;
use Cosray\Schema\Label;

#[Label('Test Rich Text Entry')]
final class TestRichTextEntry
{
	#[Label('Content')]
	private RichText $content;
}
