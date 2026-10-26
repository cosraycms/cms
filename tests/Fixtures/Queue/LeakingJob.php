<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Queue;

use Celema\Quma\Database;
use Cosray\Contract\Job;

/** Begins a transaction and never ends it. */
final class LeakingJob implements Job
{
	public function __construct(
		private readonly Database $db,
	) {}

	public function handle(array $payload): void
	{
		$this->db->begin();
	}
}
