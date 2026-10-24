<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Arg;
use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Cosray\Actor;
use Cosray\User\Tokens;
use Cosray\Users;
use DateTimeImmutable;
use Exception;
use ValueError;

#[Command('token:create', 'Creates an API token for a user', group: 'Users')]
final class CreateToken
{
	public function __construct(
		private readonly Users $users,
		private readonly Tokens $tokens,
	) {}

	public function __invoke(
		Io $io,
		#[Arg('Email address or username of the user the token acts as')]
		string $login,
		#[Opt('Where the token is used, like the name of the integration')]
		string $name = '',
		#[Opt('When the token stops working, like 2027-01-31 or 2027-01-31T18:00:00Z')]
		string $expires = '',
	): int {
		$user = $this->users->byLogin($login);

		if ($user === null) {
			$io->error('No active user with the login <strong>%s</strong>.', $login);

			return 1;
		}

		try {
			$expiry = $expires === '' ? null : new DateTimeImmutable($expires);
			$created = $this->tokens->create($user, $name, Actor::system(), $expiry);
		} catch (ValueError $e) {
			$io->error('%s.', $e->getMessage());

			return 1;
		} catch (Exception) {
			$io->error('Cannot read the expiry <strong>%s</strong>.', $expires);

			return 1;
		}

		$io->success('Created token %s for %s.', $created['uid'], $user->loginName());

		if ($expiry !== null) {
			$io->line('It expires at %s.', $expiry->format(DATE_ATOM));
		}

		$io->line('Store it now; it cannot be shown again:');
		$io->line('%s', $created['token']);

		return 0;
	}
}
