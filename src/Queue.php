<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Quma\Database;
use Cosray\Contract\Job;
use Cosray\Exception\RuntimeException;
use JsonException;

/**
 * The job queue in the site's database: pushes jobs and manages the ones
 * that failed. Runners take the jobs from there; see `docs/queue.md`.
 *
 * A push inside a transaction belongs to it. The job is queued only if the
 * transaction commits, so a write and the work it causes, like a stored
 * form submission and the mail about it, happen together or not at all.
 *
 *     $queue->push(SendReceipt::class, ['order' => $order], delay: 60);
 *
 * @api
 */
final class Queue
{
	private const int MAX_NAME_LENGTH = 64;

	public function __construct(
		private readonly Database $db,
	) {}

	/**
	 * @param class-string<Job> $job
	 * @param array<array-key, mixed> $payload handed to the job after a JSON round trip
	 * @param int $delay seconds until the job is due
	 * @param string $queue a name runners can pick jobs by
	 * @return int the job's id
	 */
	public function push(string $job, array $payload = [], int $delay = 0, string $queue = 'default'): int
	{
		if (!is_a($job, Job::class, true)) {
			throw new RuntimeException("A queued job implements Cosray\\Contract\\Job; {$job} does not.");
		}

		if ($delay < 0) {
			throw new RuntimeException('A job cannot be due in the past.');
		}

		if ($queue === '' || mb_strlen($queue) > self::MAX_NAME_LENGTH) {
			throw new RuntimeException('A queue name has 1 to ' . self::MAX_NAME_LENGTH . ' characters.');
		}

		try {
			$json = json_encode(
				$payload,
				JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
			);
		} catch (JsonException $e) {
			throw new RuntimeException("The payload for {$job} is not JSON: {$e->getMessage()}", previous: $e);
		}

		/** @var array{id: int} $row */
		$row = $this->db->queue->push([
			'queue' => $queue,
			'job' => $job,
			'payload' => $json,
			'delay' => $delay,
		])->one();

		return (int) $row['id'];
	}

	/**
	 * The payloads of a job class's jobs that have yet to succeed, in the
	 * order they were pushed; failed jobs are left out. Tests read what the
	 * code under test queued with it.
	 *
	 * @param class-string<Job> $job
	 * @return list<array<array-key, mixed>>
	 */
	public function pending(string $job): array
	{
		/** @var list<array{payload: string}> $rows */
		$rows = $this->db->queue->pending(['job' => $job])->all();

		return array_map(
			static fn(array $row): array => (array) json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR),
			$rows,
		);
	}

	/** @return list<array{id: int, queue: string, job: string, attempts: int, error: ?string, created: string, failed: string}> */
	public function failed(): array
	{
		/** @var list<array{id: int, queue: string, job: string, attempts: int, error: ?string, created: string, failed: string}> */
		return $this->db->queue->failed()->all();
	}

	/** Queues failed jobs again with fresh attempts, one or all; returns how many. */
	public function retry(?int $id = null): int
	{
		return $this->db->queue->requeue($id === null ? [] : ['id' => $id])->len();
	}

	/** Deletes the jobs that failed more than the given number of days ago; returns how many. */
	public function prune(int $days): int
	{
		return $this->db->queue->prune(['days' => max(0, $days)])->len();
	}
}
