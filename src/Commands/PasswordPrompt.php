<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Io;
use Cosray\User\PasswordPolicy;
use Cosray\User\PasswordProblem;

/**
 * Asks for a new password twice and judges it as the panel does. The
 * console speaks English, so it words the policy's problems itself.
 */
final class PasswordPrompt
{
	public function __construct(
		private readonly PasswordPolicy $policy,
	) {}

	/**
	 * The new password, or null once the error says why there is none.
	 *
	 * @param ?string ...$personal The account's email, username and name.
	 */
	public function ask(Io $io, ?string ...$personal): ?string
	{
		$password = $io->secret('Password:');

		if ($password === '') {
			$io->error('A password is required.');

			return null;
		}

		$problem = $this->policy->check($password, ...$personal);

		if ($problem !== null) {
			$io->error('%s', self::message($problem));

			return null;
		}

		if (!hash_equals($password, $io->secret('Repeat password:'))) {
			$io->error('The passwords do not match.');

			return null;
		}

		return $password;
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
				=> 'The password is too close to the email address, the username, the name or the site\'s name.',
			PasswordProblem::Breached => 'The password appears in known data breaches.',
		};
	}
}
