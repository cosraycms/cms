<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\FieldDefinition;

use Cosray\Schema\Fieldset;
use Cosray\Schema\Width;
use Cosray\Tests\Fixtures\Node\TestBaseFields;

final class InvalidWidthNode
{
	#[Fieldset]
	#[Width(101)]
	private TestBaseFields $baseFields;
}
