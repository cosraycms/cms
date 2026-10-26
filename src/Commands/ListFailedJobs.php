<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Table;
use Cosray\Queue;

#[Command('queue:failed', 'Lists the jobs that failed for good', group: 'Queue')]
final class ListFailedJobs
{
	public function __construct(
		private readonly Queue $queue,
	) {}

	public function __invoke(Io $io): int
	{
		$jobs = $this->queue->failed();

		if ($jobs === []) {
			$io->line('No failed jobs.');

			return 0;
		}

		$table = new Table();
		$table->row([
			'<strong>Id</strong>',
			'<strong>Queue</strong>',
			'<strong>Job</strong>',
			'<strong>Attempts</strong>',
			'<strong>Failed</strong>',
			'<strong>Error</strong>',
		]);
		$table->rule();

		foreach ($jobs as $job) {
			$table->row(array_map(
				static fn(int|string|null $cell): string => $io->escape((string) ($cell ?? '–')),
				[$job['id'], $job['queue'], $job['job'], $job['attempts'], $job['failed'], $job['error']],
			));
		}

		$io->write($table->render());

		return 0;
	}
}
