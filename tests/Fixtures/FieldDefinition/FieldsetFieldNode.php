<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\FieldDefinition;

use Cosray\Field\Text;
use Cosray\Schema\Fieldset;

final class FieldsetFieldNode
{
	#[Fieldset]
	private Text $title;
}
