<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RuntimeException;
use Cosray\Tests\Fixtures\RecordingLogger;
use Cosray\Tests\TestCase;
use Cosray\Webhooks;

final class WebhooksTest extends TestCase
{
	public function testEventsGoToTheHooksThatSubscribeToThem(): void
	{
		$sent = [];
		$webhooks = new Webhooks(
			[
				['url' => 'https://all.example/hook'],
				['url' => 'https://nodes.example/hook', 'events' => ['node.*'], 'secret' => 's3cret'],
				['url' => 'https://drafts.example/hook', 'events' => ['node.draft']],
			],
			new RecordingLogger(),
			self::committed(...),
			static function (array $requests) use (&$sent): array {
				$sent = $requests;

				return array_fill(0, count($requests), 204);
			},
		);

		$webhooks->record('node.updated', ['uid' => 'abc', 'type' => 'article']);
		$webhooks->deliver();

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

	public function testDeliveringEmptiesTheQueueAndLogsFailures(): void
	{
		$logger = new RecordingLogger();
		$calls = 0;
		$webhooks = new Webhooks(
			[['url' => 'https://down.example/hook']],
			$logger,
			self::committed(...),
			static function (array $requests) use (&$calls): array {
				$calls++;

				return array_fill(0, count($requests), null);
			},
		);

		$webhooks->record('node.deleted', ['uid' => 'gone', 'type' => 'page']);
		$webhooks->deliver();
		$webhooks->deliver();

		$this->assertSame(1, $calls);
		$this->assertStringContainsString('no response', $logger->records[0][1]);
	}

	public function testAnUnreachableHookIsLoggedAsWithoutResponse(): void
	{
		$logger = new RecordingLogger();
		$webhooks = new Webhooks(
			[['url' => 'http://127.0.0.1:9/hook', 'secret' => 'x']],
			$logger,
			self::committed(...),
		);

		$webhooks->record('node.created', ['uid' => 'new', 'type' => 'page']);
		$webhooks->deliver();

		$this->assertCount(1, $logger->records);
		$this->assertStringContainsString('no response', $logger->records[0][1]);
	}

	public function testHooksNeedAnHttpUrl(): void
	{
		$this->expectException(RuntimeException::class);

		new Webhooks([['url' => 'ftp://files.example/hook']], new RecordingLogger(), self::committed(...));
	}

	public function testTheConfigTakesAListOfHooks(): void
	{
		$this->expectException(RuntimeException::class);

		Webhooks::fromConfig(
			$this->config(['webhooks' => ['url' => 'https://x.example']]),
			new RecordingLogger(),
			$this->db(),
		);
	}

	public function testEventsOfTransactionsThatDidNotCommitAreDropped(): void
	{
		$sent = [];
		$asked = [];
		$webhooks = new Webhooks(
			[['url' => 'https://all.example/hook']],
			new RecordingLogger(),
			static function (string $id) use (&$asked): bool {
				$asked[] = $id;

				return $id === 'committed';
			},
			static function (array $requests) use (&$sent): array {
				$sent = $requests;

				return array_fill(0, count($requests), 204);
			},
		);

		$webhooks->record('node.updated', ['uid' => 'own'], null);
		$webhooks->record('node.updated', ['uid' => 'kept'], 'committed');
		$webhooks->record('node.deleted', ['uid' => 'kept'], 'committed');
		$webhooks->record('node.updated', ['uid' => 'undone'], 'rolled-back');
		$webhooks->settle();
		$webhooks->deliver();

		$this->assertSame(
			['own', 'kept', 'kept'],
			array_map(static fn(array $request): string => json_decode($request['body'], true)['uid'], $sent),
		);
		$this->assertSame(['committed', 'rolled-back'], $asked);
	}

	private static function committed(string $id): bool
	{
		return true;
	}
}
