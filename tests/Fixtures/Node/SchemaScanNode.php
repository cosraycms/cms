<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Option;
use Cosray\Field\Text;
use Cosray\Schema\Badge;
use Cosray\Schema\Description;
use Cosray\Schema\Label;
use Cosray\Schema\Options;

#[Label('Reservation form'), Badge('Beta')]
class SchemaScanNode
{
	#[Label('Arrival day')]
	#[Description('The day the guest arrives')]
	public Text $arrival;

	#[Label('Room type')]
	#[Options([
		['value' => 'single', 'label' => 'Single room'],
		['value' => 'double', 'label' => 'Double room'],
	])]
	public Option $room;

	// Plain-string options double as their own value and are not translatable.
	#[Options(['red', 'green', 'blue'])]
	public Option $color;

	public Text $untouched;
}
