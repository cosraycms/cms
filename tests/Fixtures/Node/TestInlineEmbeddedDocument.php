<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;

final class TestInlineEmbeddedDocument
{
	private Text $before;
	private TestBaseFields $baseFields;
	private Text $after;
}
