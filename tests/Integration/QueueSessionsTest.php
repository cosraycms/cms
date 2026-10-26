<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Console\Buffer;
use Celema\Console\Io;
use Celema\Quma\Database;
use Cosray\Commands\ListenQueue;
use Cosray\Queue;
use Cosray\Queue\Listener;
use Cosray\Queue\Runner;
use Cosray\Tests\Fixtures\Queue\LeakingJob;
use Cosray\Tests\Fixtures\Queue\RecordingJob;
use Cosray\Tests\Fixtures\RecordingLogger;
use Cosray\Tests\IntegrationTestCase;

/**
 * Runners in separate sessions only see committed jobs, so these jobs are
 * committed under a queue name of their own and deleted afterwards.
 *
 * @internal
 *
 * @coversNothing
 */
final class QueueSessionsTest extends IntegrationTestCase
{
	protected bool $useTransactions = false;
	private string $name;

	protected function setUp(): void
	{
		parent::setUp();

		RecordingJob::$handled = [];
		$this->name = 'test-' . bin2hex(random_bytes(6));
	}

	protected function tearDown(): void
	{
		try {
			$this->db()->execute('DELETE FROM cms.jobs WHERE queue = :queue', ['queue' => $this->name])->run();
		} finally {
			parent::tearDown();
		}
	}

	public function testRunnersInTwoSessionsNeverTakeTheSameJob(): void
	{
		$queue = new Queue($this->db());
		$first = $queue->push(RecordingJob::class, ['n' => 1], queue: $this->name);
		$queue->push(RecordingJob::class, ['n' => 2], queue: $this->name);
		$other = new Database($this->conn());

		try {
			// The other session's claim stays uncommitted, as if its job were still running.
			$other->begin();
			$claimed = $other->queue->claim(['lease' => Runner::LEASE, 'queue' => $this->name])->one();
			$this->assertSame($first, $claimed['id']);

			$this->assertSame(1, $this->runner()->run(queue: $this->name)['done']);
			$this->assertSame([['n' => 2]], RecordingJob::$handled);
		} finally {
			$other->disconnect();
		}
	}

	public function testAJobThatLeavesATransactionOpenIsRolledBackAndRunsAgain(): void
	{
		$id = new Queue($this->db())->push(LeakingJob::class, queue: $this->name);

		$this->assertSame(1, $this->runner()->run(queue: $this->name)['retried']);
		$this->assertFalse($this->db()->getConn()->inTransaction());
		$this->assertSame(
			['state' => 'pending', 'error' => 'The job left a transaction open.'],
			$this->db()->execute('SELECT state, error FROM cms.jobs WHERE id = :id', ['id' => $id])->one(),
		);
	}

	public function testAListenerWakesOnceAPushCommits(): void
	{
		$listener = Listener::fromConnection($this->conn());
		$other = new Database($this->conn());

		try {
			$this->assertFalse($listener->wait(0.05));

			$other->begin();
			new Queue($other)->push(RecordingJob::class, queue: $this->name);
			new Queue($other)->push(RecordingJob::class, queue: $this->name);
			$this->assertFalse($listener->wait(0.05));

			$other->commit();
			$this->assertTrue($listener->wait(5.0));
			// Both pushes wake it once.
			$this->assertFalse($listener->wait(0.05));
		} finally {
			$other->disconnect();
		}
	}

	public function testTheDaemonRunsJobsUntilALimitStopsIt(): void
	{
		$queue = new Queue($this->db());
		$queue->push(RecordingJob::class, ['n' => 1], queue: $this->name);
		$queue->push(RecordingJob::class, ['n' => 2], queue: $this->name);
		$queue->push(RecordingJob::class, ['n' => 3], queue: $this->name);

		$jobs = new Buffer();
		$this->assertSame(0, $this->daemon()(new Io($jobs), queue: $this->name, maxJobs: 2));
		$this->assertSame('Ran 2 job(s), stopped by its job limit.', trim($jobs->output()));
		$this->assertSame([1, 2], array_column(RecordingJob::$handled, 'n'));

		$time = new Buffer();
		$start = hrtime(true);
		$this->assertSame(0, $this->daemon()(new Io($time), queue: $this->name, maxTime: 1));
		$this->assertSame('Ran 1 job(s), stopped by its time limit.', trim($time->output()));
		$this->assertGreaterThanOrEqual(0.9, (hrtime(true) - $start) / 1e9);
	}

	public function testABusyDaemonChecksItsLimitsBeforeEveryJob(): void
	{
		$queue = new Queue($this->db());
		$queue->push(RecordingJob::class, ['n' => 1], queue: $this->name);
		$queue->push(RecordingJob::class, ['n' => 2], queue: $this->name);

		$output = new Buffer();
		$this->assertSame(0, $this->daemon()(new Io($output), queue: $this->name, memory: 1));
		$this->assertSame('Ran 0 job(s), stopped by its memory limit.', trim($output->output()));
		$this->assertSame([], RecordingJob::$handled);
	}

	public function testASignalStopsTheDaemonBetweenJobs(): void
	{
		if (!function_exists('posix_kill') || !function_exists('pcntl_signal')) {
			$this->markTestSkipped('Needs the posix and pcntl extensions.');
		}

		new Queue($this->db())->push(RecordingJob::class, queue: $this->name);
		$daemon = new ListenQueue(
			function (): Runner {
				posix_kill(getmypid(), SIGTERM);

				return $this->runner();
			},
			Listener::fromConnection($this->conn()),
		);

		$output = new Buffer();
		$this->assertSame(0, $daemon(new Io($output), queue: $this->name));
		$this->assertSame('Ran 0 job(s), stopped by a signal.', trim($output->output()));
		$this->assertSame([], RecordingJob::$handled);
	}

	private function daemon(): ListenQueue
	{
		return new ListenQueue($this->runner(...), Listener::fromConnection($this->conn()));
	}

	private function runner(): Runner
	{
		return new Runner(
			$this->db(),
			new RecordingLogger(),
			fn(string $class): object => $class === LeakingJob::class ? new LeakingJob($this->db()) : new $class(),
		);
	}
}
