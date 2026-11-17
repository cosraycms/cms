<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Database;
use Celema\Verba\Verba;
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

	public function testServicesTranslateAsAJobDoes(): void
	{
		$this->service(Queue::class);

		$this->assertNotNull(Verba::translator());
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

	public function testWorkAfterTheCommitRunsAsItWouldOutsideATest(): void
	{
		$db = $this->service(Database::class);
		$calls = [];
		$record = static function (string $call) use (&$calls): \Closure {
			return static function () use (&$calls, $call): void {
				$calls[] = $call;
			};
		};

		$db->transaction(function () use ($db, $record, &$calls): void {
			$db->afterCommit($record('committed'));
			$this->assertSame([], $calls);
		});
		$db->begin();
		$db->afterCommit($record('rolled back'));
		$db->rollback();
		$db->afterCommit($record('outside'));

		$this->assertSame(['committed', 'outside'], $calls);
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

	public function testASignedInUserPostsAFormWithAnUploadToAPluginPage(): void
	{
		$file = (string) tempnam(sys_get_temp_dir(), 'upload');
		file_put_contents($file, '{"findings": []}');

		try {
			$response = $this
				->actingAs($this->user(roles: ['admin'], email: 'ada@example.com'))
				->post('/panel/test-uploads', ['note' => 'First'], files: ['file' => $file]);
		} finally {
			unlink($file);
		}

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame(
			[
				'user' => 'ada@example.com',
				'note' => 'First',
				'name' => basename($file),
				'content' => '{"findings": []}',
			],
			json_decode((string) $response->getBody(), true),
		);
	}

	public function testThePanelRefusesAUserWithoutThePermissionAndAGuest(): void
	{
		$this->assertSame(303, $this->post('/panel/test-uploads')->getStatusCode());

		$this->actingAs($this->user(roles: ['editor']));

		$this->assertSame(403, $this->post('/panel/test-uploads')->getStatusCode());
	}

	public function testASignedInRequestWithoutTheTokenIsRefused(): void
	{
		$this->actingAs($this->user(roles: ['admin']));

		$response = $this->post('/panel/test-uploads', headers: ['X-CSRF-Token' => 'forged']);

		$this->assertSame(403, $response->getStatusCode());
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
