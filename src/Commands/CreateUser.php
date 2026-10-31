<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Celema\Sire\Issue;
use Cosray\Actor;
use Cosray\Security\Policy;
use Cosray\User\Types;
use Cosray\Users;
use Cosray\Validation\Account;
use Psr\Log\LoggerInterface as Logger;

#[Command('user:create', 'Creates a user, asking for what the options leave out', group: 'Users')]
final class CreateUser
{
	public function __construct(
		private readonly Users $users,
		private readonly Types $types,
		private readonly Policy $policy,
		private readonly PasswordPrompt $passwords,
		private readonly Logger $logger,
	) {}

	/** @param list<string> $role */
	public function __invoke(
		Io $io,
		#[Opt('The email address, which is also a login')]
		string $email = '',
		#[Opt('A second login, without @')]
		string $username = '',
		#[Opt('The name shown in the panel')]
		string $name = '',
		#[Opt("The user type's handle")]
		string $type = Types::DEFAULT,
		#[Opt('A role to grant; repeat it for more, like --role=admin --role=editor')]
		array $role = [],
	): int {
		if (!$this->types->has($type)) {
			$io->error(
				'Unknown user type <strong>%s</strong>; choose from: %s.',
				$type,
				implode(', ', $this->types->handles()),
			);

			return 1;
		}

		$assignable = array_keys($this->types->roles($type, $this->policy));
		$email = $email !== '' ? $email : $io->ask('Email:');

		if ($io->interactive()) {
			$name = $name !== '' ? $name : $io->ask('Name (optional):');
			$role = $role !== []
				? $role
				: self::split($io->ask(sprintf(
					'Roles, separated by commas (%s), or none:',
					implode(', ', $assignable),
				)));
		}

		$role = array_values(array_unique($role));

		$account = [
			'email' => $email,
			'username' => $username !== '' ? $username : null,
			'name' => $name !== '' ? $name : null,
		];
		$problems = $this->problems($account, $role, $assignable);

		if ($problems !== []) {
			foreach ($problems as $problem) {
				$io->error('%s', $problem);
			}

			return 1;
		}

		$password = $this->passwords->ask($io, $account['email'], $account['username'], $account['name']);

		if ($password === null) {
			return 1;
		}

		$user = $this->users->create(
			$type,
			[...$account, 'roles' => $role, 'active' => true, 'panelLocale' => null, 'content' => []],
			$password,
			Actor::system(),
		);
		$roles = $role === [] ? 'none' : implode(', ', $role);
		$this->logger->notice('User {login} created with roles {roles} from the console', [
			'login' => $user->loginName(),
			'roles' => $roles,
		]);
		$io->success('Created user %s with roles: %s.', $user->loginName(), $roles);

		return 0;
	}

	/**
	 * @param array{email: string, username: ?string, name: ?string} $account
	 * @param list<string> $roles
	 * @param list<string> $assignable
	 * @return list<string>
	 */
	private function problems(array $account, array $roles, array $assignable): array
	{
		$problems = array_map(
			static fn(Issue $issue): string => match ($issue->path[0] ?? null) {
				'email' => 'A valid email address is required.',
				'username' => 'A username has at most 64 characters and no @ or spaces.',
				default => 'A name has at most 200 characters.',
			},
			new Account(false)->validate([...$account, 'password' => null])->issues(),
		);
		$taken = $this->users->taken($account['email'], $account['username']);

		if ($taken['email']) {
			$problems[] = sprintf('Another user already has the email address %s.', $account['email']);
		}

		if ($taken['username']) {
			$problems[] = sprintf('Another user already has the username %s.', (string) $account['username']);
		}

		$unknown = array_diff($roles, $assignable);

		if ($unknown !== []) {
			$problems[] = sprintf(
				'Unknown roles for this user type: %s; choose from: %s.',
				implode(', ', $unknown),
				implode(', ', $assignable),
			);
		}

		return $problems;
	}

	/** @return list<string> */
	private static function split(string $roles): array
	{
		return array_values(array_filter(
			array_map(trim(...), explode(',', $roles)),
			static fn(string $role): bool => $role !== '',
		));
	}
}
