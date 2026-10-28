<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Database;
use Cosray\App;
use Cosray\Exception\RuntimeException;
use Cosray\Mailer;
use Cosray\Node\Writer;
use Cosray\Queue;
use Cosray\Testing\TestCase;
use Cosray\Tests\Fixtures\Node\TestPage;
use Cosray\Tests\Fixtures\Queue\RecordingJob;
use Cosray\Tests\TestCase as CmsTestCase;
use PDO;
use PDOException;

/**
 * @internal
 *
 * @covers \Cosray\Testing\Bootstrap
 * @covers \Cosray\Testing\Database
 * @covers \Cosray\Testing\TestCase
 */
final class TestingTestCaseTest extends TestCase
{
	protected function createApp(): App
	{
		return (require dirname(__DIR__) . '/Fixtures/Testing/app.php')(CmsTestCase::databaseDsn());
	}

	public function testARequestWritesIntoTheTestsTransactionOnly(): void
	{
		$response = $this->post('/signups', ['email' => 'erika@example.com']);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame(
			[['email' => 'erika@example.com']],
			$this->service(Queue::class)->pending(RecordingJob::class),
		);

		$outside = new PDO(CmsTestCase::databaseDsn());
		$count = $outside->query("SELECT count(*) FROM cms.jobs WHERE payload->>'email' = 'erika@example.com'");
		$this->assertNotFalse($count);
		$this->assertSame(0, $count->fetchColumn());
	}

	public function testMailsStayQueued(): void
	{
		$this->post('/signups', ['email' => 'erika@example.com']);

		$mails = $this->service(Mailer::class)->queued();

		$this->assertCount(1, $mails);
		$this->assertSame('erika@example.com', $mails[0]->getTo()[0]->getAddress());
	}

	public function testAFailingTransactionUndoesOnlyItsOwnWork(): void
	{
		$this->service(Queue::class)->push(RecordingJob::class, ['email' => 'before@example.com']);

		$response = $this->post('/signups', ['email' => 'erika@example.com', 'fail' => '1']);

		$this->assertSame(500, $response->getStatusCode());
		$this->assertSame(
			[['email' => 'before@example.com']],
			$this->service(Queue::class)->pending(RecordingJob::class),
		);
		$this->assertSame([], $this->service(Mailer::class)->queued());
	}

	public function testATransactionLeftOpenEndsWithTheRequest(): void
	{
		$this->assertSame(200, $this->post('/signups/abandon')->getStatusCode());
		$this->assertSame(200, $this->post('/signups', ['email' => 'erika@example.com'])->getStatusCode());

		$this->assertSame(
			[['email' => 'erika@example.com']],
			$this->service(Queue::class)->pending(RecordingJob::class),
		);
	}

	public function testAFailedStatementLeavesTheTestUsable(): void
	{
		$this->assertSame(500, $this->post('/signups/break')->getStatusCode());
		$this->assertSame(200, $this->post('/signups', ['email' => 'erika@example.com'])->getStatusCode());

		$this->assertSame(
			[['email' => 'erika@example.com']],
			$this->service(Queue::class)->pending(RecordingJob::class),
		);
	}

	public function testTransactionsFailAsTheyWouldOutsideATest(): void
	{
		$db = $this->service(Database::class);
		$db->begin();

		try {
			$db->begin();
			$this->fail('A second begin() succeeded');
		} catch (PDOException $e) {
			$this->assertSame('There is already an active transaction', $e->getMessage());
		}

		$db->commit();
		$this->expectExceptionObject(new PDOException('There is no active transaction'));
		$db->rollback();
	}

	public function testContentWrittenAsAServiceIsServed(): void
	{
		$writer = $this->service(Writer::class);
		$writer->create(
			$writer
				->prepare(TestPage::class, ['title' => ['en' => 'Hello']])
				->published()
				->path('en', '/hello'),
		);

		$response = $this->get('/hello?ref=test');

		$this->assertSame(200, $response->getStatusCode());
		$this->assertStringContainsString('<h1>Hello</h1>', (string) $response->getBody());
	}

	public function testASessionEndsWithTheTest(): void
	{
		$this->get('/panel/login');
		$this->assertNotSame('', session_id());

		$this->tearDown();

		$this->assertSame('', session_id());
	}

	public function testTheDefaultAppIsTheOneTheProjectBoots(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage("boot/app.php does not exist; override createApp() to create the site's app.");

		parent::createApp();
	}
}
