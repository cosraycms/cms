<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Cosray\Maintenance;

#[Command('maintenance:status', 'Shows whether maintenance mode is on', group: 'Maintenance')]
final class MaintenanceStatus
{
	public function __construct(
		private readonly Maintenance $maintenance,
	) {}

	public function __invoke(Io $io): int
	{
		$state = $this->maintenance->state();

		if ($state === null) {
			$io->line('Maintenance mode is off.');

			return 0;
		}

		$io->line(
			'Maintenance mode is on%s: %s, Retry-After %d.',
			$state->since === null ? '' : ' since ' . $state->since,
			$state->full ? 'every request is refused' : 'writes are refused',
			$state->retry,
		);

		if ($state->message !== null) {
			$io->line('Message: %s', $state->message);
		}

		$io->line('File: %s', $this->maintenance->file());

		return 0;
	}
}
