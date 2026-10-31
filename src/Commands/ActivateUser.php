<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Arg;
use Celema\Console\Command;
use Celema\Console\Io;
use Cosray\Actor;
use Cosray\Users;
use Psr\Log\LoggerInterface as Logger;

#[Command('user:activate', 'Lets a deactivated user sign in again', group: 'Users')]
final class ActivateUser
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

		if ($user->active) {
			$io->line('%s is already active.', $user->loginName());

			return 0;
		}

		$this->users->setActive($user, true, Actor::system());
		$this->logger->notice('User {login} activated from the console', ['login' => $user->loginName()]);
		$io->success('Activated %s.', $user->loginName());

		return 0;
	}
}
