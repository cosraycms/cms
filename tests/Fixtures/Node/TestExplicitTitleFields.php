<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Embedded;
use Cosray\Field\Text;
use Cosray\Schema\Title;

final class TestExplicitTitleFields implements Embedded
{
	#[Title]
	private Text $heading;
}
