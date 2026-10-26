<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\JobFailed;
use Cosray\Exception\RuntimeException;
use Cosray\Tests\TestCase;
use Cosray\Webhooks\Client;
use Cosray\Webhooks\Delivery;
use Override;

/**
 * @internal
 *
 * @coversNothing
 */
final class WebhookDeliveryTest extends TestCase
{
	private const array REQUEST = [
		'url' => 'https://hooks.example/cosray',
		'headers' => ['Content-Type' => 'application/json', 'X-Cosray-Event' => 'node.updated'],
		'body' => '{"event":"node.updated"}',
	];

	public function testASuccessfulPostEndsTheJob(): void
	{
		$client = $this->client(204);
		new Delivery($client)->handle(self::REQUEST);

		$this->assertSame([[self::REQUEST['url'], self::REQUEST['headers'], self::REQUEST['body']]], $client->posted);
	}

	public function testAServerErrorOrNoResponseRunsAgainLater(): void
	{
		foreach ([
			500 => 'failed with 500',
			429 => 'failed with 429',
			0 => 'failed with no response',
		] as $status => $message) {
			try {
				new Delivery($this->client($status === 0 ? null : $status))->handle(self::REQUEST);
				$this->fail('The delivery succeeded.');
			} catch (RuntimeException $e) {
				$this->assertNotInstanceOf(JobFailed::class, $e);
				$this->assertSame("Webhook node.updated to https://hooks.example/cosray {$message}", $e->getMessage());
			}
		}
	}

	public function testARefusedPostFailsAtOnce(): void
	{
		$this->throws(JobFailed::class, 'Webhook node.updated to https://hooks.example/cosray failed with 410');

		new Delivery($this->client(410))->handle(self::REQUEST);
	}

	public function testAPayloadWithoutItsRequestFailsAtOnce(): void
	{
		$this->throws(JobFailed::class, 'lacks its url, headers or body');

		new Delivery($this->client(204))->handle(['url' => 'https://hooks.example/cosray']);
	}

	public function testTheClientReportsAnUnreachableHookAsWithoutResponse(): void
	{
		$this->assertNull(new Client()->post('http://127.0.0.1:9/hook', [], '{}'));
	}

	private function client(?int $status): Client
	{
		return new class($status) extends Client {
			/** @var list<array{string, array<string, string>, string}> */
			public array $posted = [];

			public function __construct(
				private readonly ?int $status,
			) {}

			#[Override]
			public function post(string $url, array $headers, string $body): ?int
			{
				$this->posted[] = [$url, $headers, $body];

				return $this->status;
			}
		};
	}
}
