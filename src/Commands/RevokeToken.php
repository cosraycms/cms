<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Arg;
use Celema\Console\Command;
use Celema\Console\Io;
use Cosray\User\Tokens;
use Cosray\Users;

#[Command('token:revoke', 'Revokes an API token', group: 'Users')]
final class RevokeToken
{
	public function __construct(
		private readonly Users $users,
		private readonly Tokens $tokens,
	) {}

	public function __invoke(
		Io $io,
		#[Arg('Email address or username of the token\'s user')]
		string $login,
		#[Arg('Uid of the token, as token:list shows it')]
		string $uid,
	): int {
		$user = $this->users->byLogin($login);

		if ($user === null) {
			$io->error('No active user with the login <strong>%s</strong>.', $login);

			return 1;
		}

		if (!$this->tokens->revoke($user, $uid)) {
			$io->error('%s has no token <strong>%s</strong>.', $user->loginName(), $uid);

			return 1;
		}

		$io->success('Revoked token %s.', $uid);

		return 0;
	}
}
