<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Queue;

use Cosray\Contract\Job;
use Cosray\Exception\JobFailed;

final class RejectedJob implements Job
{
	public function handle(array $payload): void
	{
		throw new JobFailed('No such recipient');
	}
}
