<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Schema\Label;
use Cosray\Tests\Fixtures\Field\TestMoney;

#[Label('Money Document')]
class TestMoneyDocument
{
	#[Label('Price')]
	public TestMoney $price;
}
