<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\FieldDefinition;

use Cosray\Schema\Label;
use Cosray\Tests\Fixtures\Node\TestBaseFields;

final class InlineLabelNode
{
	#[Label('Invalid inline label')]
	private TestBaseFields $baseFields;
}
