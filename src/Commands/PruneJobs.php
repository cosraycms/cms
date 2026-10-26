<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Cosray\Queue;

#[Command('queue:prune', 'Deletes failed jobs after a number of days', group: 'Queue')]
final class PruneJobs
{
	public function __construct(
		private readonly Queue $queue,
	) {}

	public function __invoke(
		Io $io,
		#[Opt('Keep the jobs that failed within this many days')]
		int $days = 30,
	): int {
		$io->line('Deleted %d failed job(s).', $this->queue->prune($days));

		return 0;
	}
}
