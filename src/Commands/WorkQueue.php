<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Cosray\Queue\Runner;

#[Command('queue:work', 'Runs due jobs until none is left', group: 'Queue')]
final class WorkQueue
{
	public function __construct(
		private readonly Runner $runner,
	) {}

	public function __invoke(
		Io $io,
		#[Opt('Run only the jobs pushed under this queue name')]
		string $queue = '',
		#[Opt('Stop after this many jobs; 0 sets no limit')]
		int $maxJobs = 0,
		#[Opt('Start no job after this many seconds; 0 sets no limit')]
		int $maxTime = 0,
	): int {
		$counts = $this->runner->run(
			queue: $queue === '' ? null : $queue,
			jobs: $maxJobs > 0 ? $maxJobs : null,
			seconds: $maxTime > 0 ? (float) $maxTime : null,
		);

		// Cron mails any output, so a run that found nothing stays silent.
		if (array_sum($counts) > 0) {
			$io->line(
				'%d done, %d to retry later, %d failed.',
				$counts['done'],
				$counts['retried'],
				$counts['failed'],
			);
		}

		return 0;
	}
}
