<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celemas\Quma\Commands\Command;
use Cosray\References\Rebuild;

class References extends Command
{
	protected string $group = 'Database';
	protected string $prefix = 'db';
	protected string $name = 'references';
	protected string $description = 'Rebuilds the derived asset and node reference indexes from content';

	public function run(): int
	{
		$result = new Rebuild($this->env->db)->run();

		echo
			"Reference indexes rebuilt: {$result['assets']} asset references, "
				. "{$result['nodes']} node references from {$result['owners']} owners"
				. ($result['skipped'] > 0 ? " ({$result['skipped']} dangling uids skipped)" : '')
				. "\n"
		;

		return 0;
	}
}
