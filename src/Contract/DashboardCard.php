<?php

declare(strict_types=1);

namespace Cosray\Contract;

use Cosray\Panel\Dashboard\Card;

interface DashboardCard
{
	public function card(): ?Card;
}
