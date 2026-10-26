<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Queue;

use Cosray\Contract\Job;
use RuntimeException;

final class FailingJob implements Job
{
	public function handle(array $payload): void
	{
		throw new RuntimeException('The mail server is down');
	}
}
