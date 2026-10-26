<?php

declare(strict_types=1);

namespace Cosray\Contract;

/**
 * Work that `Cosray\Queue` runs outside the request that pushed it.
 *
 * The runner builds the job through the container, so its constructor
 * gets services, but never the request: a job may run after the response,
 * from cron or in a daemon. A job runs at least once and may run again if
 * its runner dies before recording the result. An exception makes it run
 * again later; `Cosray\Exception\JobFailed` fails it without retries.
 */
interface Job
{
	/** @param array<array-key, mixed> $payload as pushed, decoded from JSON */
	public function handle(array $payload): void;
}
