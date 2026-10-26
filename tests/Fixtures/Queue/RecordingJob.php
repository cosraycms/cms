<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Queue;

use Cosray\Contract\Job;

final class RecordingJob implements Job
{
	/** @var list<array<array-key, mixed>> */
	public static array $handled = [];

	public function handle(array $payload): void
	{
		self::$handled[] = $payload;
	}
}
