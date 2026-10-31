<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Celema\Console\Table;
use Cosray\Users;

#[Command('user:list', 'Lists the users, deactivated ones included', group: 'Users')]
final class ListUsers
{
	public function __construct(
		private readonly Users $users,
	) {}

	public function __invoke(
		Io $io,
		#[Opt('Only users of this type, by its handle')]
		string $type = '',
		#[Opt('Only users whose email, username or name contains this text')]
		string $search = '',
	): int {
		$type = $type === '' ? null : $type;
		$total = $this->users->count($type, $search);

		if ($total === 0) {
			$io->line('No users found.');

			return 0;
		}

		$table = new Table();
		$table->row([
			'<strong>Username</strong>',
			'<strong>Email</strong>',
			'<strong>Name</strong>',
			'<strong>Type</strong>',
			'<strong>Roles</strong>',
			'<strong>Active</strong>',
		]);
		$table->rule();

		foreach ($this->users->list($type, $search, $total, 0) as $user) {
			$table->row(array_map($io->escape(...), [
				$user->username !== '' ? $user->username : '–',
				$user->email,
				$user->name ?? '–',
				$user->type,
				$user->roles === [] ? '–' : implode(', ', $user->roles),
				$user->active ? 'yes' : 'no',
			]));
		}

		$io->write($table->render());

		return 0;
	}
}
