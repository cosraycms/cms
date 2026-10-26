<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Closure;
use Cosray\Queue\Listener;
use Cosray\Queue\Runner;

#[Command('queue:listen', 'Runs jobs as they are pushed, until stopped', group: 'Queue')]
final class ListenQueue
{
	/** Seconds between looks for jobs that became due without a notification, like retries. */
	private const float MAX_WAIT = 60.0;

	// Keeps a job whose lock another runner holds for a moment from spinning the loop.
	private const float MIN_WAIT = 1.0;

	private const array SIGNALS = [SIGTERM, SIGINT];

	private bool $stop = false;

	/** @param Closure(): Runner $runner makes a runner, anew for every batch of jobs */
	public function __construct(
		private readonly Closure $runner,
		private readonly Listener $listener,
	) {}

	public function __invoke(
		Io $io,
		#[Opt('Run only the jobs pushed under this queue name')]
		string $queue = '',
		#[Opt('Stop after this many seconds, for the process manager to start it anew; 0 sets no limit')]
		int $maxTime = 0,
		#[Opt('Stop after this many jobs; 0 sets no limit')]
		int $maxJobs = 0,
		#[Opt('Stop once the process uses this many megabytes')]
		int $memory = 128,
	): int {
		$name = $queue === '' ? null : $queue;
		$start = self::now();
		$ran = 0;
		$this->stop = false;
		$async = $this->trapSignals();

		try {
			while (true) {
				$runner = ($this->runner)();

				while (!$this->stop && !self::reached($maxJobs, $ran) && array_sum($runner->run($name, jobs: 1)) > 0) {
					$ran++;
				}

				$limit = match (true) {
					$this->stop => 'a signal',
					self::reached($maxJobs, $ran) => 'its job limit',
					$maxTime > 0 && (self::now() - $start) >= $maxTime => 'its time limit',
					memory_get_usage(true) >= ($memory * 1024 * 1024) => 'its memory limit',
					default => null,
				};

				if ($limit !== null) {
					$io->line('Ran %d job(s), stopped by %s.', $ran, $limit);

					return 0;
				}

				$next = $runner->next($name);
				$wait = $next === null ? self::MAX_WAIT : min(self::MAX_WAIT, max(self::MIN_WAIT, $next));

				if ($maxTime > 0) {
					$wait = min($wait, $maxTime - (self::now() - $start));
				}

				unset($runner);
				$this->listener->wait($wait);
			}
		} finally {
			$this->releaseSignals($async);
		}
	}

	/** Lets a signal stop the loop between jobs; returns whether signals were handled asynchronously before. */
	private function trapSignals(): ?bool
	{
		if (!function_exists('pcntl_signal')) {
			return null;
		}

		foreach (self::SIGNALS as $signal) {
			pcntl_signal($signal, function (): void {
				$this->stop = true;
			});
		}

		return pcntl_async_signals(true);
	}

	private function releaseSignals(?bool $async): void
	{
		if ($async === null) {
			return;
		}

		foreach (self::SIGNALS as $signal) {
			pcntl_signal($signal, SIG_DFL);
		}

		pcntl_async_signals($async);
	}

	private static function reached(int $limit, int $count): bool
	{
		return $limit > 0 && $count >= $limit;
	}

	private static function now(): float
	{
		return hrtime(true) / 1e9;
	}
}
