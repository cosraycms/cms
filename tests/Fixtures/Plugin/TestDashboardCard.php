<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Plugin;

use Cosray\Contract\DashboardCard;
use Cosray\Panel\Dashboard\Card;

final class TestDashboardCard implements DashboardCard
{
	public function card(): Card
	{
		return new Card('Plugin card', 'Ready');
	}
}
