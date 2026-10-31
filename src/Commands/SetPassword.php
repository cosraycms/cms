<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Arg;
use Celema\Console\Command;
use Celema\Console\Io;
use Cosray\Actor;
use Cosray\Users;
use Psr\Log\LoggerInterface as Logger;

#[Command('user:password', "Sets a user's password, ending their sessions", group: 'Users')]
final class SetPassword
{
	public function __construct(
		private readonly Users $users,
		private readonly PasswordPrompt $passwords,
		private readonly Logger $logger,
	) {}

	public function __invoke(
		Io $io,
		#[Arg('Email address or username of the user')]
		string $login,
	): int {
		$user = $this->users->findByLogin($login);

		if ($user === null) {
			$io->error('No user with the login <strong>%s</strong>.', $login);

			return 1;
		}

		$password = $this->passwords->ask(
			$io,
			$user->email,
			$user->username !== '' ? $user->username : null,
			$user->name,
		);

		if ($password === null) {
			return 1;
		}

		$this->users->setPassword($user, $password, Actor::system());
		$this->logger->notice('Password of {login} set from the console', ['login' => $user->loginName()]);
		$io->success('Set the password of %s; their sessions and remembered logins have ended.', $user->loginName());

		return 0;
	}
}
