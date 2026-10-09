<?php

declare(strict_types=1);

namespace Cosray\Commands;

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

	public function __invoke(Io $io): int
	{
		$io->line("Create a superuser\n");
		$email = $io->ask('Email:');

		if ($email === '') {
			$io->error('An email address is required. Aborting.');

			return 1;
		}

		$name = $io->ask('Name:');
		$password = $io->secret('Password:');

		if ($password === '') {
			$io->error('A password is required. Aborting.');

			return 1;
		}

		if (!hash_equals($password, $io->secret('Repeat password:'))) {
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
			$io->error('%s', $e->getMessage());

			return 1;
		}

		$io->success('Successfully created superuser: %s', $email);

		return 0;
	}
}
