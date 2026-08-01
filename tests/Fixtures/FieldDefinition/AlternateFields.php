<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\FieldDefinition;

use Cosray\Contract\Embedded;
use Cosray\Field\Text;

final class AlternateFields implements Embedded
{
	private Text $alternate;
}
