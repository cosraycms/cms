<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Console\Runtime;
use Cosray\Node\Writer;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Node\TestDocument;
use Cosray\Util\Transaction;
use Cosray\Webhooks\Delivery;
use RuntimeException;

/**
 * @internal
 *
 * @coversNothing
 */
final class ApiWebhooksTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->db()->execute('DELETE FROM cms.jobs')->run();
		$this->app = $this->createApp([
			'api.path' => '/api',
			'webhooks' => [['url' => 'https://hooks.example/cosray']],
			// The tests read the queued deliveries.
			'queue.after_response' => false,
		]);
		$this->loadFixtures('basic-types');
		$this->authenticateAs('editor');
	}

	public function testWritesQueueTheirEvents(): void
	{
		$this->send('PUT', '/api/nodes/hooked', [
			'type' => 'test-document',
			'content' => ['title' => ['value' => ['zxx' => 'Hooked']]],
		]);
		$this->send('PATCH', '/api/nodes/hooked', ['published' => true]);
		$this->makeRequest('DELETE', '/api/nodes/hooked');

		$events = $this->events();

		$this->assertSame(['node.created', 'node.updated', 'node.deleted'], array_column($events, 'event'));
		$this->assertSame(['hooked'], array_values(array_unique(array_column($events, 'uid'))));
		$this->assertSame('test-document', $events[0]['type']);
	}

	public function testARejectedWriteReportsNothing(): void
	{
		$this->send('PUT', '/api/nodes/rejected', [
			'type' => 'test-document',
			'content' => ['title' => ['value' => ['zxx' => 'no']]],
		]);

		$this->assertSame([], $this->events());
	}

	public function testWritesOutsideARequestQueueTheirEventsToo(): void
	{
		// Built by the container, as a job or a command gets it.
		$writer = new Runtime($this->app->container())->get(Writer::class);

		$writer->create($writer->prepare(TestDocument::class, ['title' => 'From a job'])->uid('from-a-job'));

		$this->assertSame([['node.created', 'from-a-job']], array_map(
			static fn(array $event): array => [$event['event'], $event['uid']],
			$this->events(),
		));
	}

	public function testAWriteUndoneWithItsTransactionReportsNothing(): void
	{
		$writer = new Runtime($this->app->container())->get(Writer::class);

		try {
			Transaction::run($this->db(), static function () use ($writer): never {
				$writer->create($writer->prepare(TestDocument::class, ['title' => 'Undone'])->uid('undone'));

				throw new RuntimeException('The site changed its mind.');
			});
		} catch (RuntimeException $e) {
			$this->assertSame('The site changed its mind.', $e->getMessage());
		}

		$this->assertSame([], $this->events());
	}

	/** @return list<array<string, mixed>> the bodies of the queued deliveries */
	private function events(): array
	{
		$rows = $this->db()->execute(
			'SELECT payload::text AS payload FROM cms.jobs WHERE job = :job ORDER BY id',
			['job' => Delivery::class],
		)->all();

		return array_map(
			static fn(array $row): array => json_decode(json_decode($row['payload'], true)['body'], true),
			$rows,
		);
	}

	/** @param array<string, mixed> $body */
	private function send(string $method, string $uri, array $body): void
	{
		$this->makeRequest($method, $uri, [
			'body' => json_encode($body),
			'headers' => ['Content-Type' => 'application/json'],
		]);
	}
}
