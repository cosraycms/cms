<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Console\Buffer;
use Celema\Console\Io;
use Cosray\Commands\ListUsers;
use Cosray\Tests\IntegrationTestCase;
use Cosray\Users;
use Override;

final class UserCommandsTest extends IntegrationTestCase
{
	private Users $users;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->users = new Users($this->db());
	}

	public function testListsUsersWithTheirTypeRolesAndState(): void
	{
		$this->createTestUser([
			'username' => 'cli-anna',
			'email' => 'anna@example.com',
			'data' => ['name' => 'Anna Cli'],
		]);
		$this->createTestUser([
			'username' => 'cli-ben',
			'email' => 'ben@example.com',
			'active' => false,
			'role' => 'superuser',
		]);

		$buffer = new Buffer();
		$this->assertSame(0, (new ListUsers($this->users))(new Io($buffer), search: 'cli-'));
		$lines = array_map(
			static fn(string $line): array => preg_split('/\s{2,}/', trim($line)) ?: [],
			array_values(array_filter(
				explode("\n", $buffer->output()),
				static fn(string $line): bool => str_contains($line, 'cli-'),
			)),
		);

		$this->assertSame(
			[
				['cli-anna', 'anna@example.com', 'Anna Cli', 'user', 'editor', 'yes'],
				['cli-ben', 'ben@example.com', 'Test User', 'user', 'superuser', 'no'],
			],
			$lines,
		);
	}

	public function testSaysSoWhenNoUserMatches(): void
	{
		$buffer = new Buffer();

		$this->assertSame(0, (new ListUsers($this->users))(new Io($buffer), search: 'nobody-at-all'));
		$this->assertStringContainsString('No users found.', $buffer->output());
	}
}
