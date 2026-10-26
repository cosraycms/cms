<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Database;
use Cosray\Queue;
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

	private function runner(): Runner
	{
		return new Runner(
			$this->db(),
			new RecordingLogger(),
			fn(string $class): object => $class === LeakingJob::class ? new LeakingJob($this->db()) : new $class(),
		);
	}
}
