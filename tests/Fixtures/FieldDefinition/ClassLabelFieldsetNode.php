<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\FieldDefinition;

use Cosray\Schema\Fieldset;
use Cosray\Tests\Fixtures\Node\TestBaseFields;

final class ClassLabelFieldsetNode
{
	#[Fieldset]
	private TestBaseFields $baseFields;
}
