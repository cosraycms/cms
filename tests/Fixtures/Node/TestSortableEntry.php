<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\DateTime;
use Cosray\Field\Number;
use Cosray\Field\Text;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Title;

#[Label('Sortable entry'), Handle('test-sortable-entry'), Title('lastName')]
final class TestSortableEntry
{
	public Text $lastName;
	public Text $firstName;
	public Number $amount;
	public DateTime $start;
}
