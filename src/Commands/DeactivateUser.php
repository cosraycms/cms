<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Arg;
use Celema\Console\Command;
use Celema\Console\Io;
use Cosray\Actor;
use Cosray\Users;
use Psr\Log\LoggerInterface as Logger;

#[Command('user:deactivate', 'Keeps a user from signing in, ending their sessions', group: 'Users')]
final class DeactivateUser
{
	public function __construct(
		private readonly Users $users,
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

		if (!$user->active) {
			$io->line('%s is already deactivated.', $user->loginName());

			return 0;
		}

		if ($this->users->isLastSuperuser($user)) {
			$io->error('%s is the last active superuser; activate or create another one first.', $user->loginName());

			return 1;
		}

		$this->users->setActive($user, false, Actor::system());
		$this->logger->notice('User {login} deactivated from the console', ['login' => $user->loginName()]);
		$io->success('Deactivated %s; their sessions and remembered logins have ended.', $user->loginName());

		return 0;
	}
}
