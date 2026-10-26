<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Exception\RuntimeException;
use Cosray\Queue;
use Cosray\Queue\Runner;
use Cosray\Tests\Fixtures\Queue\FailingJob;
use Cosray\Tests\Fixtures\Queue\RecordingJob;
use Cosray\Tests\Fixtures\Queue\RejectedJob;
use Cosray\Tests\Fixtures\RecordingLogger;
use Cosray\Tests\IntegrationTestCase;
use Cosray\Util\Transaction;
use stdClass;

/**
 * @internal
 *
 * @coversNothing
 */
final class QueueTest extends IntegrationTestCase
{
	private Queue $queue;
	private RecordingLogger $logger;

	protected function setUp(): void
	{
		parent::setUp();

		RecordingJob::$handled = [];
		$this->queue = new Queue($this->db());
		$this->logger = new RecordingLogger();
		// Committed jobs left in the test database would mix in; the delete is rolled back with the test.
		$this->db()->execute('DELETE FROM cms.jobs')->run();
	}

	public function testAJobRunsWithItsPayloadAndIsRemoved(): void
	{
		$id = $this->queue->push(RecordingJob::class, ['to' => 'reader@example.com', 'amount' => 1.0, 'ü' => 'ß']);

		$this->assertSame(['done' => 1, 'retried' => 0, 'failed' => 0], $this->runner()->run());
		$this->assertSame([['to' => 'reader@example.com', 'amount' => 1.0, 'ü' => 'ß']], RecordingJob::$handled);
		$this->assertNull($this->job($id));
	}

	public function testAJobPushedInWorkThatIsUndoneIsNeverQueued(): void
	{
		try {
			Transaction::run($this->db(), function (): void {
				$this->queue->push(RecordingJob::class);

				throw new RuntimeException('The submission was invalid');
			});
		} catch (RuntimeException $e) {
			$this->assertSame('The submission was invalid', $e->getMessage());
		}

		$this->assertSame(['done' => 0, 'retried' => 0, 'failed' => 0], $this->runner()->run());
		$this->assertSame([], RecordingJob::$handled);
	}

	public function testADelayedJobWaitsUntilItIsDue(): void
	{
		$id = $this->queue->push(RecordingJob::class, delay: 60);

		$this->runner()->run();
		$this->assertSame([], RecordingJob::$handled);

		$this->makeDue($id);
		$this->runner()->run();
		$this->assertCount(1, RecordingJob::$handled);
	}

	public function testAFailedAttemptRunsAgainLaterWithGrowingDelays(): void
	{
		$id = $this->queue->push(FailingJob::class);

		$this->assertSame(['done' => 0, 'retried' => 1, 'failed' => 0], $this->runner()->run());
		$job = $this->job($id);
		$this->assertSame('pending', $job['state']);
		$this->assertSame(1, $job['attempts']);
		$this->assertSame(60, $job['wait']);
		$this->assertSame('RuntimeException: The mail server is down', $job['error']);
		$this->assertSame(
			[
				'warning',
				"Job {$id} ("
					. FailingJob::class
					. ') failed attempt 1, next in 60 seconds: RuntimeException: The mail server is down',
			],
			$this->logger->records[0],
		);

		// Not due yet.
		$this->assertSame(['done' => 0, 'retried' => 0, 'failed' => 0], $this->runner()->run());

		$this->makeDue($id);
		$this->runner()->run();
		$this->assertSame(300, $this->job($id)['wait'] ?? null);
	}

	public function testAJobFailsForGoodOnceItsAttemptsAreUsedUp(): void
	{
		$id = $this->queue->push(FailingJob::class);
		$this->db()->execute('UPDATE cms.jobs SET attempts = 5 WHERE id = :id', ['id' => $id])->run();

		$this->assertSame(['done' => 0, 'retried' => 0, 'failed' => 1], $this->runner()->run());
		$job = $this->job($id);
		$this->assertSame('failed', $job['state']);
		$this->assertSame(6, $job['attempts']);
		$this->assertNotNull($job['failed']);
		$this->assertSame(
			[
				'error',
				"Job {$id} ("
					. FailingJob::class
					. ') failed after 6 attempts: RuntimeException: The mail server is down',
			],
			$this->logger->records[0],
		);
		$this->assertSame([$id], array_column($this->queue->failed(), 'id'));
	}

	public function testAJobThatCannotSucceedFailsAtOnce(): void
	{
		$id = $this->queue->push(RejectedJob::class);

		$this->assertSame(['done' => 0, 'retried' => 0, 'failed' => 1], $this->runner()->run());
		$this->assertSame('failed', $this->job($id)['state'] ?? null);
		$this->assertSame('Cosray\Exception\JobFailed: No such recipient', $this->job($id)['error'] ?? null);
	}

	public function testAJobIsTakenOverOnceTheLeaseOfItsDeadRunnerEnds(): void
	{
		$held = $this->queue->push(RecordingJob::class, ['lease' => 'held']);
		$ended = $this->queue->push(RecordingJob::class, ['lease' => 'ended']);
		$this->db()->execute(
			"UPDATE cms.jobs SET state = 'running', attempts = 1,
				due = now() + CASE WHEN id = :held THEN interval '1 minute' ELSE interval '-1 second' END
			WHERE id IN (:held, :ended)",
			['held' => $held, 'ended' => $ended],
		)->run();

		$this->runner()->run();

		$this->assertSame([['lease' => 'ended']], RecordingJob::$handled);
		$this->assertSame('running', $this->job($held)['state'] ?? null);
	}

	public function testAJobWhoseRunnerDiedDuringItsLastAttemptFails(): void
	{
		$id = $this->queue->push(RecordingJob::class);
		$this->db()->execute(
			"UPDATE cms.jobs SET state = 'running', attempts = 6, due = now() - interval '1 second' WHERE id = :id",
			['id' => $id],
		)->run();

		$this->assertSame(['done' => 0, 'retried' => 0, 'failed' => 1], $this->runner()->run());
		$this->assertSame([], RecordingJob::$handled);
		$this->assertSame('Its runner stopped during the last attempt.', $this->job($id)['error'] ?? null);
	}

	public function testAJobOfAnUnknownClassFails(): void
	{
		$id = $this->insert('App\Job\Gone');

		$this->assertSame(['done' => 0, 'retried' => 0, 'failed' => 1], $this->runner()->run());
		$this->assertSame('App\Job\Gone is not a job class.', $this->job($id)['error'] ?? null);
	}

	public function testAJobTheContainerCannotBuildRunsAgainLater(): void
	{
		$id = $this->queue->push(RecordingJob::class);
		$runner = new Runner($this->db(), $this->logger, static fn(string $class): object => new stdClass());

		$this->assertSame(['done' => 0, 'retried' => 1, 'failed' => 0], $runner->run());
		$this->assertSame(
			'Cosray\Exception\RuntimeException: Resolving ' . RecordingJob::class . ' returned stdClass.',
			$this->job($id)['error'] ?? null,
		);
	}

	public function testARunStopsAtItsJobAndTimeLimits(): void
	{
		$this->queue->push(RecordingJob::class, ['n' => 1]);
		$this->queue->push(RecordingJob::class, ['n' => 2]);
		$this->queue->push(RecordingJob::class, ['n' => 3]);

		$this->assertSame(['done' => 2, 'retried' => 0, 'failed' => 0], $this->runner()->run(jobs: 2));

		$time = 0.0;
		$clock = static function () use (&$time): float {
			return $time += 1.0;
		};
		$this->queue->push(RecordingJob::class, ['n' => 4]);

		$this->assertSame(1, $this->runner($clock)->run(seconds: 1.5)['done']);
		$this->assertSame([1, 2, 3], array_column(RecordingJob::$handled, 'n'));
	}

	public function testARunCanBeLimitedToOneQueue(): void
	{
		$this->queue->push(RecordingJob::class, ['queue' => 'default']);
		$this->queue->push(RecordingJob::class, ['queue' => 'mail'], queue: 'mail');

		$this->runner()->run(queue: 'mail');

		$this->assertSame([['queue' => 'mail']], RecordingJob::$handled);
	}

	public function testPushRefusesWhatItCannotQueue(): void
	{
		$this->assertRefused(fn(): int => $this->queue->push(stdClass::class), 'implements Cosray\Contract\Job');
		$this->assertRefused(
			fn(): int => $this->queue->push(RecordingJob::class, delay: -1),
			'cannot be due in the past',
		);
		$this->assertRefused(fn(): int => $this->queue->push(RecordingJob::class, queue: ''), 'queue name');
		$this->assertRefused(
			fn(): int => $this->queue->push(RecordingJob::class, ['bytes' => "\xB1"]),
			'payload for ' . RecordingJob::class . ' is not JSON',
		);
	}

	public function testFailedJobsAreRetriedOrPruned(): void
	{
		$first = $this->queue->push(RejectedJob::class);
		$second = $this->queue->push(RejectedJob::class);
		$this->runner()->run();

		$this->assertSame(1, $this->queue->retry($first));
		$this->assertSame('pending', $this->job($first)['state'] ?? null);
		$this->assertSame(0, $this->job($first)['attempts'] ?? null);
		$this->assertNull($this->job($first)['error'] ?? null);
		$this->assertSame(0, $this->queue->retry($first));

		$this->db()->execute(
			"UPDATE cms.jobs SET failed = now() - interval '31 days' WHERE id = :id",
			['id' => $second],
		)->run();
		$this->assertSame(1, $this->queue->prune(30));
		$this->assertNull($this->job($second));
		$this->assertSame(0, $this->queue->retry());
	}

	/** @param ?\Closure(): float $clock */
	private function runner(?\Closure $clock = null): Runner
	{
		return new Runner($this->db(), $this->logger, static fn(string $class): object => new $class(), $clock);
	}

	/** @return ?array{state: string, attempts: int, error: ?string, failed: ?string, wait: int} */
	private function job(int $id): ?array
	{
		/** @var ?array{state: string, attempts: int, error: ?string, failed: ?string, wait: int} */
		return $this->db()->execute(
			'SELECT state, attempts, error, failed, extract(epoch FROM due - now())::int AS wait
			FROM cms.jobs WHERE id = :id',
			['id' => $id],
		)->first();
	}

	private function makeDue(int $id): void
	{
		$this->db()->execute("UPDATE cms.jobs SET due = now() - interval '1 second' WHERE id = :id", [
			'id' => $id,
		])->run();
	}

	private function insert(string $job): int
	{
		/** @var array{id: int} $row */
		$row = $this->db()->execute('INSERT INTO cms.jobs (job) VALUES (:job) RETURNING id', ['job' => $job])->one();

		return $row['id'];
	}

	/** @param callable(): mixed $push */
	private function assertRefused(callable $push, string $message): void
	{
		try {
			$push();
			$this->fail('The push went through.');
		} catch (RuntimeException $e) {
			$this->assertStringContainsString($message, $e->getMessage());
		}
	}
}
