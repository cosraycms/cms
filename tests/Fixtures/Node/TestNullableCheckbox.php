<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Checkbox;
use Cosray\Schema\DefaultValue;
use Cosray\Schema\Nullable;

class TestNullableCheckbox
{
	#[Nullable, DefaultValue(true)]
	public Checkbox $flag;
}
