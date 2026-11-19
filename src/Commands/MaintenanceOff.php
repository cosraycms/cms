<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Cosray\Maintenance;
use Throwable;

#[Command('maintenance:off', 'Ends maintenance mode', group: 'Maintenance')]
final class MaintenanceOff
{
	public function __construct(
		private readonly Maintenance $maintenance,
	) {}

	public function __invoke(Io $io): int
	{
		try {
			$wasOn = $this->maintenance->off();
		} catch (Throwable $e) {
			$io->error($e->getMessage());

			return 1;
		}

		$io->success($wasOn ? 'Maintenance mode is off.' : 'Maintenance mode was not on.');

		return 0;
	}
}
