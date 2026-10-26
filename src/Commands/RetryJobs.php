<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Arg;
use Celema\Console\Command;
use Celema\Console\Io;
use Cosray\Queue;

#[Command('queue:retry', 'Queues failed jobs again with fresh attempts', group: 'Queue')]
final class RetryJobs
{
	public function __construct(
		private readonly Queue $queue,
	) {}

	public function __invoke(
		Io $io,
		#[Arg('The id of a failed job, or "all"')]
		string $job,
	): int {
		if ($job !== 'all' && !ctype_digit($job)) {
			$io->error('Pass the id of a failed job or <strong>all</strong>.');

			return 1;
		}

		$count = $this->queue->retry($job === 'all' ? null : (int) $job);

		if ($count === 0 && $job !== 'all') {
			$io->error('No failed job with the id <strong>%s</strong>.', $job);

			return 1;
		}

		$io->line('Queued %d job(s) again.', $count);

		return 0;
	}
}
