<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Tests\IntegrationTestCase;
use Cosray\Webhooks;
use Cosray\Webhooks\Delivery;

/**
 * @internal
 *
 * @coversNothing
 */
final class WebhooksQueueTest extends IntegrationTestCase
{
	public function testConfiguredHooksGetTheirEventsThroughTheQueue(): void
	{
		$this->db()->execute('DELETE FROM cms.jobs')->run();
		$webhooks = Webhooks::fromConfig(
			$this->config(['webhooks' => [['url' => 'https://hooks.example/cosray', 'secret' => 's3cret']]]),
			$this->db(),
		);

		$webhooks->record('node.created', ['uid' => 'queued', 'type' => 'page']);

		$jobs = $this->db()->execute('SELECT queue, job, payload::text AS payload FROM cms.jobs')->all();
		$this->assertCount(1, $jobs);
		$this->assertSame(['webhooks', Delivery::class], [$jobs[0]['queue'], $jobs[0]['job']]);
		$payload = json_decode($jobs[0]['payload'], true);
		$this->assertSame('https://hooks.example/cosray', $payload['url']);
		$this->assertSame('queued', json_decode($payload['body'], true)['uid']);
		$this->assertSame(
			'sha256=' . hash_hmac('sha256', $payload['body'], 's3cret'),
			$payload['headers']['X-Cosray-Signature'],
		);
	}
}
