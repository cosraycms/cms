<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Security\RateLimiter;
use Cosray\Tests\IntegrationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RateLimiterTest extends IntegrationTestCase
{
	public function testAttemptsOverTheLimitWaitUntilTheWindowEnds(): void
	{
		$limiter = $this->limiter();

		$this->assertSame([0, 0, 0], array_map(
			static fn(): int => $limiter->attempt(['form:192.0.2.1' => 3], 60),
			range(1, 3),
		));
		$this->assertSame(60, $limiter->attempt(['form:192.0.2.1' => 3], 60));
		$this->assertSame(0, $limiter->attempt(['form:192.0.2.2' => 3], 60));

		$this->db()->execute("UPDATE cms.rate_limits SET ends = now() - interval '1 second'")->run();

		$this->assertSame(0, $limiter->attempt(['form:192.0.2.1' => 3], 60));
	}

	public function testTheFullestBucketDecides(): void
	{
		$limiter = $this->limiter();
		$limiter->attempt(['narrow' => 1], 30);

		$this->assertSame(30, $limiter->attempt(['wide' => 10, 'narrow' => 1], 60));
	}

	public function testReleasedAndClearedAttemptsNoLongerCount(): void
	{
		$limiter = $this->limiter();
		$limiter->attempt(['form' => 2], 60);
		$limiter->attempt(['form' => 2], 60);
		$limiter->release('form');

		$this->assertSame(0, $limiter->attempt(['form' => 2], 60));

		$limiter->clear('form');

		$this->assertSame(0, $limiter->attempt(['form' => 2], 60));
		$this->assertSame(0, $limiter->attempt(['form' => 2], 60));
	}

	public function testCountersKeepNoAddressesAndEndedOnesGo(): void
	{
		$limiter = $this->limiter();
		$limiter->attempt(['sign-in:192.0.2.1' => 5], 60);
		$this->db()->execute("UPDATE cms.rate_limits SET ends = now() - interval '1 second'")->run();

		$limiter->attempt(['sign-in:192.0.2.2' => 5], 60);

		$buckets = array_column($this->db()->execute('SELECT bucket FROM cms.rate_limits')->all(), 'bucket');
		$this->assertCount(1, $buckets);
		$this->assertStringNotContainsString('192.0.2', $buckets[0]);
	}

	private function limiter(): RateLimiter
	{
		return new RateLimiter($this->db(), $this->config(['app.secret' => 'rate-limiter-test-secret']));
	}
}
