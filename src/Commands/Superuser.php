<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Quma\Connection;
use Celema\Quma\Database;
use Cosray\Uid;
use Cosray\User\PasswordPolicy;
use Cosray\User\PasswordProblem;
use Throwable;

#[Command('add-superuser', 'Add a superuser')]
class Superuser
{
	protected Database $db;

	public function __construct(
		Connection $connection,
		private readonly PasswordPolicy $passwords,
	) {
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

		$problem = $this->passwords->check($password, $email, $name);

		if ($problem !== null) {
			$io->error('%s Aborting.', self::message($problem));

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

	private static function message(PasswordProblem $problem): string
	{
		return match ($problem) {
			PasswordProblem::Short => sprintf(
				'The password is too short: use at least %d characters.',
				PasswordPolicy::MIN_LENGTH,
			),
			PasswordProblem::Long => sprintf(
				'The password is too long: use at most %d characters.',
				PasswordPolicy::MAX_LENGTH,
			),
			PasswordProblem::Guessable
				=> 'The password is too easy to guess: sequences like 1234 or qwertz and repeated characters or parts barely count.',
			PasswordProblem::Personal
				=> 'The password is too close to the email address, the name or the site\'s name.',
			PasswordProblem::Breached => 'The password appears in known data breaches.',
		};
	}
}
