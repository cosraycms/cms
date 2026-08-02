<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;
use Cosray\Schema\Label;

final class TestConditionalEmbeddedDocument
{
	#[Label('Mode')]
	private Text $mode;

	private TestConditionalFields $conditionalFields;
}
