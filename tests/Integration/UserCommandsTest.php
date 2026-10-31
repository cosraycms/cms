<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Console\Buffer;
use Celema\Console\Io;
use Cosray\Commands\CreateUser;
use Cosray\Commands\ListUsers;
use Cosray\Commands\PasswordPrompt;
use Cosray\Security\Policy;
use Cosray\Tests\Fixtures\RecordingLogger;
use Cosray\Tests\IntegrationTestCase;
use Cosray\User\PasswordPolicy;
use Cosray\User\Types;
use Cosray\Users;
use Override;

final class UserCommandsTest extends IntegrationTestCase
{
	private const string PASSWORD = 'correct horse battery staple';

	private Users $users;
	private RecordingLogger $logger;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->users = new Users($this->db());
		$this->logger = new RecordingLogger();
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

	public function testCreatesAUserFromOptionsAndAPipedPassword(): void
	{
		$buffer = new Buffer(self::PASSWORD . "\n" . self::PASSWORD . "\n");

		$exit = $this->create()(
			new Io($buffer),
			email: 'cli-root@example.com',
			name: 'Root Cli',
			role: ['superuser'],
		);
		$user = $this->users->byLogin('cli-root@example.com');

		$this->assertSame(0, $exit);
		$this->assertStringContainsString(
			'Created user cli-root@example.com with roles: superuser.',
			$buffer->output(),
		);
		$this->assertNotNull($user);
		$this->assertSame(['superuser'], $user->roles);
		$this->assertSame('Root Cli', $user->name);
		$this->assertTrue($user->active);
		$this->assertTrue(password_verify(self::PASSWORD, $user->password));
		$this->assertSame(
			[['notice', 'User cli-root@example.com created with roles superuser from the console']],
			$this->logger->records,
		);
	}

	public function testAsksForWhatTheOptionsLeaveOut(): void
	{
		$buffer = new Buffer(
			"cli-asked@example.com\nAsked Cli\nadmin, editor\n" . self::PASSWORD . "\n" . self::PASSWORD . "\n",
			interactive: true,
		);

		$this->assertSame(0, $this->create()(new Io($buffer), username: 'cli-asked'));

		$user = $this->users->byLogin('cli-asked');

		$this->assertNotNull($user);
		$this->assertSame('cli-asked@example.com', $user->email);
		$this->assertSame('Asked Cli', $user->name);
		$this->assertSame(['admin', 'editor'], $user->roles);
	}

	public function testRefusesTakenLoginsUnknownRolesAndTypesBeforeAskingForAPassword(): void
	{
		$this->createTestUser(['username' => 'cli-taken', 'email' => 'taken@example.com']);

		$taken = new Buffer();
		$this->assertSame(1, $this->create()(
			new Io($taken),
			email: 'taken@example.com',
			username: 'cli-taken',
			role: ['owner'],
		));
		$this->assertStringContainsString('already has the email address taken@example.com', $taken->errorOutput());
		$this->assertStringContainsString('already has the username cli-taken', $taken->errorOutput());
		$this->assertStringContainsString('Unknown roles for this user type: owner', $taken->errorOutput());
		$this->assertStringNotContainsString('Password:', $taken->output());

		$invalid = new Buffer();
		$this->assertSame(1, $this->create()(new Io($invalid), email: 'not an address'));
		$this->assertStringContainsString('A valid email address is required.', $invalid->errorOutput());

		$type = new Buffer();
		$this->assertSame(1, $this->create()(new Io($type), email: 'cli-typed@example.com', type: 'robot'));
		$this->assertStringContainsString('Unknown user type', $type->errorOutput());
	}

	public function testRefusesAWeakOrUnrepeatedPassword(): void
	{
		$weak = new Buffer("123456789012\n");
		$this->assertSame(1, $this->create()(new Io($weak), email: 'cli-weak@example.com'));
		$this->assertStringContainsString('The password is too easy to guess', $weak->errorOutput());

		$mismatch = new Buffer(self::PASSWORD . "\nsomething else\n");
		$this->assertSame(1, $this->create()(new Io($mismatch), email: 'cli-weak@example.com'));
		$this->assertStringContainsString('The passwords do not match.', $mismatch->errorOutput());

		$this->assertNull($this->users->byLogin('cli-weak@example.com'));
	}

	private function create(): CreateUser
	{
		return new CreateUser(
			$this->users,
			new Types(),
			Policy::withDefaults(),
			new PasswordPrompt(new PasswordPolicy($this->config(), $this->logger)),
			$this->logger,
		);
	}
}
