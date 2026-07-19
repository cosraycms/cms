<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Quma\Connection;
use Celema\Quma\Database;
use Cosray\References\Rebuild;

#[Command(
	'db:references',
	'Rebuilds the derived asset and node reference indexes from content',
	group: 'Database',
)]
class References
{
	private readonly Database $db;

	public function __construct(Connection $conn)
	{
		$this->db = new Database($conn);
	}

	public function __invoke(Args $args, Io $io): int
	{
		$result = new Rebuild($this->db)->run();

		echo
			"Reference indexes rebuilt: {$result['assets']} asset references, "
				. "{$result['nodes']} node references from {$result['owners']} owners"
				. ($result['skipped'] > 0 ? " ({$result['skipped']} dangling uids skipped)" : '')
				. "\n"
		;

		return 0;
	}
}
