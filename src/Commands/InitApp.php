<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Console\Io;

#[Command('init-app', 'Initialize the Cosray app')]
class InitApp
{
	public function __invoke(Args $args, Io $io): int
	{
		return 0;
	}
}
