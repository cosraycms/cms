<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Queue;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Queue\RecordingJob;

/**
 * @internal
 *
 * @coversNothing
 */
final class QueueAfterResponseTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		RecordingJob::$handled = [];
		$this->db()->execute('DELETE FROM cms.jobs')->run();
	}

	public function testDueJobsRunOnceTheResponseIsSent(): void
	{
		new Queue($this->db())->push(RecordingJob::class, ['after' => 'response']);

		$this->makeRequest('GET', '/');

		$this->assertSame([['after' => 'response']], RecordingJob::$handled);
	}

	public function testRequestsLeaveJobsAloneWhenACronOrDaemonRunsThem(): void
	{
		$this->app = $this->createApp(['queue.after_response' => false]);
		new Queue($this->db())->push(RecordingJob::class);

		$this->makeRequest('GET', '/');

		$this->assertSame([], RecordingJob::$handled);
	}
}
