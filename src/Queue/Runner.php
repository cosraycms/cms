<?php

declare(strict_types=1);

namespace Cosray\Queue;

use Celema\Quma\Database;
use Closure;
use Cosray\Contract\Job;
use Cosray\Exception\JobFailed;
use Cosray\Exception\RuntimeException;
use Psr\Log\LoggerInterface as Logger;
use Throwable;

/**
 * Runs due jobs one at a time, after a response or from `queue:work`.
 *
 * A claimed job holds a lease, so runners in several processes never run
 * it at once; when the lease ends before the job does, its runner is
 * presumed dead and the job is due again. A failed attempt is retried
 * later, with growing delays, until the job's attempts are used up.
 *
 * @internal
 *
 * @phpstan-type Claimed array{id: int, queue: string, job: string, payload: string, attempts: int, max_attempts: int}
 */
final class Runner
{
	/** Seconds a claimed job may run before another runner takes it over. */
	public const int LEASE = 300;

	/** Seconds before the second, third and later attempts. */
	public const array BACKOFF = [60, 300, 1800, 7200, 43200];

	private const int MAX_ERROR_LENGTH = 4000;

	/**
	 * @param Closure(class-string<Job>): object $resolve builds a job
	 * @param ?Closure(): float $clock seconds, for the time limit
	 */
	public function __construct(
		private readonly Database $db,
		private readonly Logger $logger,
		private readonly Closure $resolve,
		private readonly ?Closure $clock = null,
	) {}

	/**
	 * Runs jobs until none is due or a limit is reached. The time limit is
	 * checked between jobs; a running job is never cut short.
	 *
	 * @param ?string $queue only jobs pushed under this name
	 * @param ?int $jobs at most this many jobs
	 * @param ?float $seconds no new job after this many seconds
	 * @return array{done: int, retried: int, failed: int}
	 */
	public function run(?string $queue = null, ?int $jobs = null, ?float $seconds = null): array
	{
		$clock = $this->clock ?? static fn(): float => hrtime(true) / 1e9;
		$start = $clock();
		$counts = ['done' => 0, 'retried' => 0, 'failed' => 0];

		while ($jobs === null || array_sum($counts) < $jobs) {
			if ($seconds !== null && ($clock() - $start) >= $seconds) {
				break;
			}

			$claimed = $this->claim($queue);

			if ($claimed === null) {
				break;
			}

			$counts[$this->process($claimed)]++;
		}

		return $counts;
	}

	/** @return ?Claimed */
	private function claim(?string $queue): ?array
	{
		$params = ['lease' => self::LEASE];

		if ($queue !== null) {
			$params['queue'] = $queue;
		}

		/** @var ?Claimed */
		return $this->db->queue->claim($params)->first();
	}

	/**
	 * @param Claimed $claimed
	 * @return 'done'|'retried'|'failed'
	 */
	private function process(array $claimed): string
	{
		if ($claimed['attempts'] > $claimed['max_attempts']) {
			return $this->fail($claimed, 'Its runner stopped during the last attempt.');
		}

		$class = $claimed['job'];

		if (!is_a($class, Job::class, true)) {
			return $this->fail($claimed, "{$class} is not a job class.");
		}

		$wasInTransaction = $this->db->getConn()->inTransaction();

		try {
			$job = ($this->resolve)($class);

			if (!$job instanceof Job) {
				throw new RuntimeException("Resolving {$class} returned " . get_debug_type($job) . '.');
			}

			$payload = json_decode($claimed['payload'], true, flags: JSON_THROW_ON_ERROR);
			$job->handle(is_array($payload) ? $payload : []);
		} catch (JobFailed $e) {
			$this->endTransaction($wasInTransaction);

			return $this->fail($claimed, self::message($e));
		} catch (Throwable $e) {
			$this->endTransaction($wasInTransaction);

			return $this->retry($claimed, self::message($e));
		}

		if ($this->endTransaction($wasInTransaction)) {
			return $this->retry($claimed, 'The job left a transaction open.');
		}

		$this->db->queue->done(['id' => $claimed['id']])->run();

		return 'done';
	}

	/** Rolls back a transaction the job opened and left open; returns whether there was one. */
	private function endTransaction(bool $wasInTransaction): bool
	{
		if ($wasInTransaction || !$this->db->getConn()->inTransaction()) {
			return false;
		}

		$this->db->rollback();

		return true;
	}

	/**
	 * @param Claimed $claimed
	 * @return 'retried'|'failed'
	 */
	private function retry(array $claimed, string $error): string
	{
		if ($claimed['attempts'] >= $claimed['max_attempts']) {
			return $this->fail($claimed, $error);
		}

		$delay = self::BACKOFF[min($claimed['attempts'], count(self::BACKOFF)) - 1];
		$this->db->queue->retry(['id' => $claimed['id'], 'delay' => $delay, 'error' => $error])->run();
		$this->logger->warning('Job {id} ({job}) failed attempt {attempt}, next in {delay} seconds: {error}', [
			'id' => $claimed['id'],
			'job' => $claimed['job'],
			'attempt' => $claimed['attempts'],
			'delay' => $delay,
			'error' => $error,
		]);

		return 'retried';
	}

	/**
	 * @param Claimed $claimed
	 * @return 'failed'
	 */
	private function fail(array $claimed, string $error): string
	{
		$this->db->queue->fail(['id' => $claimed['id'], 'error' => $error])->run();
		$this->logger->error('Job {id} ({job}) failed after {attempts} attempts: {error}', [
			'id' => $claimed['id'],
			'job' => $claimed['job'],
			'attempts' => min($claimed['attempts'], $claimed['max_attempts']),
			'error' => $error,
		]);

		return 'failed';
	}

	private static function message(Throwable $e): string
	{
		return mb_substr($e::class . ': ' . $e->getMessage(), 0, self::MAX_ERROR_LENGTH);
	}
}
