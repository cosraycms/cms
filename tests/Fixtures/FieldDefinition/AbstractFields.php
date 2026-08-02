<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\FieldDefinition;

use Cosray\Contract\Embedded;
use Cosray\Field\Text;

abstract class AbstractFields implements Embedded
{
	protected Text $title;
}
