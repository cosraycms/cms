<?php

declare(strict_types=1);

namespace Cosray\Security;

use Celema\Quma\Database;
use Cosray\Config;

/**
 * Counts attempts per bucket in fixed windows, like failed sign-ins per
 * client address. A window starts with the first attempt in a bucket. The
 * database stores buckets as an HMAC with `app.secret`, so its counters
 * hold no addresses or logins.
 *
 * @internal
 */
final class RateLimiter
{
	public function __construct(
		private readonly Database $db,
		private readonly Config $config,
	) {}

	/**
	 * Counts an attempt in each bucket. Returns 0 while every bucket is
	 * within its limit, else the seconds until the attempt is allowed again.
	 *
	 * @param array<string, positive-int> $limits the most attempts per window by bucket
	 * @param positive-int $window seconds
	 */
	public function attempt(array $limits, int $window): int
	{
		$wait = 0;

		foreach ($limits as $bucket => $limit) {
			/** @var array{hits: int, seconds: int} $counter */
			$counter = $this->db->rateLimits->hit([
				'bucket' => $this->hash((string) $bucket),
				'window' => $window,
			])->one();

			if ($counter['hits'] === 1) {
				$this->db->rateLimits->prune()->run();
			}

			if ($counter['hits'] > $limit) {
				$wait = max($wait, $counter['seconds'], 1);
			}
		}

		return $wait;
	}

	/** Takes back one attempt, like one that turned out to be legitimate. */
	public function release(string $bucket): void
	{
		$this->db->rateLimits->release(['bucket' => $this->hash($bucket)])->run();
	}

	public function clear(string $bucket): void
	{
		$this->db->rateLimits->clear(['bucket' => $this->hash($bucket)])->run();
	}

	private function hash(string $bucket): string
	{
		return hash_hmac('sha256', $bucket, $this->config->app->secret ?? '');
	}
}
