<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Celema\Container\Container;
use Cosray\Tests\End2EndTestCase;
use Cosray\Webhooks;

/**
 * @internal
 *
 * @coversNothing
 */
final class ApiWebhooksTest extends End2EndTestCase
{
	/** @var list<array{url: string, headers: array<string, string>, body: string}> */
	private array $sent = [];

	protected function setUp(): void
	{
		parent::setUp();

		$this->app = $this->createApp(['api.path' => '/api']);
		$this->loadFixtures('basic-types');
		$this->authenticateAs('editor');
	}

	public function testCommittedWritesAreReportedWhenTheRequestEnds(): void
	{
		$this->send('PUT', '/api/nodes/hooked', [
			'type' => 'test-document',
			'content' => ['title' => ['value' => ['zxx' => 'Hooked']]],
		]);
		$this->send('PATCH', '/api/nodes/hooked', ['published' => true]);
		$this->makeRequest('DELETE', '/api/nodes/hooked');

		$events = array_map(static fn(array $request): array => json_decode($request['body'], true), $this->sent);

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

		$this->assertSame([], $this->sent);
	}

	public function container(): Container
	{
		$container = parent::container();

		$container->add(Webhooks::class, new Webhooks(
			[['url' => 'https://hooks.example/cosray']],
			// Requests join the test's transaction, which stands in for the commit.
			static fn(string $id): bool => true,
			function (array $request): void {
				$this->sent[] = $request;
			},
		));

		return $container;
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
