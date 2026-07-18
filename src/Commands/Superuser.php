<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Quma\Connection;
use Celema\Quma\Database;

class Superuser extends Command
{
	protected string $group = 'General';
	protected string $name = 'add-superuser';
	protected string $description = 'Add a superuser';
	protected Database $db;

	public function __construct(Connection $connection)
	{
		$this->db = new Database($connection);
	}

	public function run(Args $args): int
	{
		$params = [];

		echo "Create a superuser\n\n";
		$params['email'] = readline('Email: ');
		$params['full_name'] = readline('Full Name: ');
		$params['display_name'] = readline('Display Name: ');
		$params['password'] = password_hash(
			readline('Password: '),
			PASSWORD_ARGON2ID,
		);

		$result = $this->db->users->addSuperuser($params);

		if ($result['success']) {
			echo "\nSuccessfully created superuser: " . $params['email'] . "\n";
		} else {
			echo "\nError occured. Please review your data!\n";
			echo $result['message'] . "\n";
		}

		return 0;
	}
}
