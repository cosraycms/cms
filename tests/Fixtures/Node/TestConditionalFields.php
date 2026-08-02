<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Embedded;
use Cosray\Field\Checkbox;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\When;

final class TestConditionalFields implements Embedded
{
	#[Label('Extended')]
	protected Checkbox $extended;

	#[Label('Details')]
	#[When('extended')]
	protected Text $details;

	#[Label('Extra')]
	#[When('mode', 'full')]
	protected Text $extra;
}
