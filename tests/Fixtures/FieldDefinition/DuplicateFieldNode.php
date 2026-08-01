<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\FieldDefinition;

use Cosray\Field\Text;
use Cosray\Tests\Fixtures\Node\TestBaseFields;

final class DuplicateFieldNode
{
	private Text $title;
	private TestBaseFields $baseFields;
}
