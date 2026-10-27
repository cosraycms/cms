<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Console\Buffer;
use Celema\Console\Io;
use Cosray\Commands\Superuser;
use Cosray\Tests\IntegrationTestCase;
use Cosray\User\PasswordPolicy;

/**
 * @internal
 *
 * @coversNothing
 */
final class SuperuserCommandTest extends IntegrationTestCase
{
	// The command opens its own connection, so its insert would not join the
	// test transaction; the created row is cleaned up manually instead.
	protected bool $useTransactions = false;

	public function testCreateASuperuser(): void
	{
		$email = 'su-' . uniqid() . '@example.com';

		try {
			[$exit, $io] = $this->runCommand("{$email}\nSuper User\ncorrect horse battery\ncorrect horse battery\n");

			$this->assertSame(0, $exit);
			$this->assertStringContainsString(
				"Successfully created superuser: {$email}",
				$io->output(),
			);

			$user = $this->db()->execute(
				'SELECT * FROM cms.users WHERE email = :email',
				['email' => $email],
			)->one();

			$this->assertSame('{superuser}', $user['roles']);
			$this->assertTrue((bool) $user['active']);
			$this->assertTrue(password_verify('correct horse battery', (string) $user['password']));
			$this->assertSame(
				['name' => 'Super User'],
				json_decode((string) $user['data'], associative: true),
			);
		} finally {
			$this->db()->execute(
				'DELETE FROM cms.users WHERE email = :email',
				['email' => $email],
			)->run();
		}
	}

	public function testRequireAnEmail(): void
	{
		[$exit, $io] = $this->runCommand("\n");

		$this->assertSame(1, $exit);
		$this->assertStringContainsString('An email address is required', $io->errorOutput());
	}

	public function testRequireAPassword(): void
	{
		[$exit, $io] = $this->runCommand("su@example.com\nSuper User\n\n");

		$this->assertSame(1, $exit);
		$this->assertStringContainsString('A password is required', $io->errorOutput());
	}

	public function testRejectAGuessablePassword(): void
	{
		[$exit, $io] = $this->runCommand("su@example.com\nSuper User\n123456789012\n");

		$this->assertSame(1, $exit);
		$this->assertStringContainsString('The password is too easy to guess', $io->errorOutput());
	}

	public function testRejectMismatchedPasswords(): void
	{
		[$exit, $io] = $this->runCommand("su@example.com\nSuper User\ncorrect horse battery\nother\n");

		$this->assertSame(1, $exit);
		$this->assertStringContainsString('The passwords do not match', $io->errorOutput());
	}

	/** @return array{0: int, 1: Buffer} */
	private function runCommand(string $input): array
	{
		$buffer = new Buffer($input);
		$passwords = new PasswordPolicy();
		$exit = (new Superuser($this->conn(), $passwords))(new Io($buffer));

		return [$exit, $buffer];
	}
}
