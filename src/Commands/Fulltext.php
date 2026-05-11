<?php

declare(strict_types=1);

namespace Celemas\Cms\Commands;

use Celemas\Quma\Commands\Command;
use Celemas\Quma\Database;

class Fulltext extends Command
{
	protected string $group = 'Database';
	protected string $prefix = 'db';
	protected string $name = 'fulltext';
	protected string $description = 'Updates the fulltext index';

	public function run(): int
	{
		$this->env
			->db
			->fulltext
			->clean();
		$this->update($this->env->db);

		return 0;
	}

	private function update(Database $db): void
	{
		foreach ($db->fulltext->nodes()->lazy() as $node) {
			$json = json_decode($node['content'], true);
			error_log(print_r($json, true));
		}
	}
}
