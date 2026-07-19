<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Quma\Connection;
use Celema\Quma\Database;
use Cosray\Uid;
use Throwable;

#[Command('add-superuser', 'Add a superuser')]
class Superuser
{
	protected Database $db;

	public function __construct(Connection $connection)
	{
		$this->db = new Database($connection);
	}

	public function __invoke(Args $args, Io $io): int
	{
		$io->echoln("Create a superuser\n");
		$email = $io->ask('Email:');

		if ($email === '') {
			$io->error('An email address is required. Aborting.');

			return 1;
		}

		$name = $io->ask('Name:');
		$password = $io->ask('Password:', hidden: true);

		if ($password === '') {
			$io->error('A password is required. Aborting.');

			return 1;
		}

		if (!hash_equals($password, $io->ask('Repeat password:', hidden: true))) {
			$io->error('The passwords do not match. Aborting.');

			return 1;
		}

		try {
			$this->db->users->addSuperuser([
				'uid' => new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13)->generate(),
				'email' => $email,
				'password' => password_hash($password, PASSWORD_ARGON2ID),
				'data' => json_encode(['name' => $name], JSON_THROW_ON_ERROR),
			])->run();
		} catch (Throwable $e) {
			$io->error('Error occurred. Please review your data!');
			$io->error($e->getMessage());

			return 1;
		}

		$io->success("Successfully created superuser: {$email}");

		return 0;
	}
}
