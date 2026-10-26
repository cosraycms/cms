<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RuntimeException;
use Cosray\Tests\TestCase;
use Cosray\Webhooks;

final class WebhooksTest extends TestCase
{
	/** @var list<array{url: string, headers: array<string, string>, body: string}> */
	private array $queued = [];

	public function testEventsGoToTheHooksThatSubscribeToThem(): void
	{
		$webhooks = $this->webhooks([
			['url' => 'https://all.example/hook'],
			['url' => 'https://nodes.example/hook', 'events' => ['node.*'], 'secret' => 's3cret'],
			['url' => 'https://drafts.example/hook', 'events' => ['node.draft']],
		]);

		$webhooks->record('node.updated', ['uid' => 'abc', 'type' => 'article']);
		$webhooks->deliver();

		$sent = $this->queued;
		$this->assertSame(['https://all.example/hook', 'https://nodes.example/hook'], array_column($sent, 'url'));
		$body = json_decode($sent[1]['body'], true);
		$this->assertSame('node.updated', $body['event']);
		$this->assertSame('abc', $body['uid']);
		$this->assertSame($body['id'], $sent[1]['headers']['X-Cosray-Delivery']);
		$this->assertSame(
			'sha256=' . hash_hmac('sha256', $sent[1]['body'], 's3cret'),
			$sent[1]['headers']['X-Cosray-Signature'],
		);
		$this->assertArrayNotHasKey('X-Cosray-Signature', $sent[0]['headers']);
	}

	public function testDeliveringQueuesEachEventOnce(): void
	{
		$webhooks = $this->webhooks([['url' => 'https://down.example/hook']]);

		$webhooks->record('node.deleted', ['uid' => 'gone', 'type' => 'page']);
		$webhooks->deliver();
		$webhooks->deliver();

		$this->assertCount(1, $this->queued);
	}

	public function testHooksNeedAnHttpUrl(): void
	{
		$this->expectException(RuntimeException::class);

		$this->webhooks([['url' => 'ftp://files.example/hook']]);
	}

	public function testTheConfigTakesAListOfHooks(): void
	{
		$this->expectException(RuntimeException::class);

		Webhooks::fromConfig($this->config(['webhooks' => ['url' => 'https://x.example']]), $this->db());
	}

	public function testEventsOfTransactionsThatDidNotCommitAreDropped(): void
	{
		$asked = [];
		$webhooks = new Webhooks(
			[['url' => 'https://all.example/hook']],
			static function (string $id) use (&$asked): bool {
				$asked[] = $id;

				return $id === 'committed';
			},
			$this->queue(...),
		);

		$webhooks->record('node.updated', ['uid' => 'own'], null);
		$webhooks->record('node.updated', ['uid' => 'kept'], 'committed');
		$webhooks->record('node.deleted', ['uid' => 'kept'], 'committed');
		$webhooks->record('node.updated', ['uid' => 'undone'], 'rolled-back');
		$webhooks->settle();
		$webhooks->deliver();

		$this->assertSame(
			['own', 'kept', 'kept'],
			array_map(static fn(array $request): string => json_decode($request['body'], true)['uid'], $this->queued),
		);
		$this->assertSame(['committed', 'rolled-back'], $asked);
	}

	/** @param list<array{url: string, secret?: ?string, events?: list<string>}> $hooks */
	private function webhooks(array $hooks): Webhooks
	{
		return new Webhooks($hooks, static fn(string $id): bool => true, $this->queue(...));
	}

	/** @param array{url: string, headers: array<string, string>, body: string} $request */
	private function queue(array $request): void
	{
		$this->queued[] = $request;
	}
}
