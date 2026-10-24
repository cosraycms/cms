<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Arg;
use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Table;
use Cosray\User\Tokens;
use Cosray\Users;

#[Command('token:list', "Lists a user's API tokens", group: 'Users')]
final class ListTokens
{
	public function __construct(
		private readonly Users $users,
		private readonly Tokens $tokens,
	) {}

	public function __invoke(
		Io $io,
		#[Arg('Email address or username of the user')]
		string $login,
	): int {
		$user = $this->users->byLogin($login);

		if ($user === null) {
			$io->error('No active user with the login <strong>%s</strong>.', $login);

			return 1;
		}

		$tokens = $this->tokens->list($user);

		if ($tokens === []) {
			$io->line('%s has no API tokens.', $user->loginName());

			return 0;
		}

		$table = new Table();
		$table->row([
			'<strong>Uid</strong>',
			'<strong>Name</strong>',
			'<strong>Created</strong>',
			'<strong>Expires</strong>',
			'<strong>Last used</strong>',
		]);
		$table->rule();

		foreach ($tokens as $token) {
			$table->row(array_map(
				static fn(?string $cell): string => $io->escape($cell ?? '–'),
				[$token['uid'], $token['name'], $token['created'], $token['expires'], $token['used']],
			));
		}

		$io->write($table->render());

		return 0;
	}
}
