<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Quma\Connection;
use Celema\Quma\Database;

#[Command('db:fulltext', 'Updates the fulltext index', group: 'Database')]
class Fulltext
{
	private readonly Database $db;

	public function __construct(Connection $conn)
	{
		$this->db = new Database($conn);
	}

	public function __invoke(Args $args, Io $io): int
	{
		$this->db->fulltext->clean();
		$this->update($this->db);

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
