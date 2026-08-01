<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\FieldDefinition;

use Cosray\Schema\Fieldset;

final class LabellessFieldsetNode
{
	#[Fieldset]
	private UnlabelledFields $fields;
}
