<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Console\Buffer;
use Celema\Console\Io;
use Cosray\Commands\ActivateUser;
use Cosray\Commands\CreateUser;
use Cosray\Commands\DeactivateUser;
use Cosray\Commands\ListUsers;
use Cosray\Commands\LoginLink;
use Cosray\Commands\PasswordPrompt;
use Cosray\Commands\SetPassword;
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

	public function testSetsThePasswordOfAnyUserAndEndsTheirRememberedLogins(): void
	{
		$active = $this->createTestUser(['username' => 'cli-pass', 'email' => 'pass@example.com']);
		$this->createTestUser(['username' => 'cli-off', 'email' => 'off@example.com', 'active' => false]);
		$this->users->remember(hash('sha256', 'remembered'), $active, '2099-01-01 00:00:00');
		$this->assertNotNull($this->users->bySession(hash('sha256', 'remembered')));

		$buffer = new Buffer(self::PASSWORD . "\n" . self::PASSWORD . "\n");
		$this->assertSame(0, $this->setPassword()(new Io($buffer), 'pass@example.com'));
		$this->assertTrue(password_verify(self::PASSWORD, $this->users->byLogin('cli-pass')->password ?? ''));
		$this->assertNull($this->users->bySession(hash('sha256', 'remembered')));
		$this->assertSame([['notice', 'Password of cli-pass set from the console']], $this->logger->records);

		$inactive = new Buffer(self::PASSWORD . "\n" . self::PASSWORD . "\n");
		$this->assertSame(0, $this->setPassword()(new Io($inactive), 'cli-off'));
		$this->assertTrue(password_verify(self::PASSWORD, $this->users->findByLogin('cli-off')->password ?? ''));
	}

	public function testRefusesAnUnknownLoginOrAWeakPassword(): void
	{
		$this->createTestUser(['username' => 'cli-weak-pass']);

		$unknown = new Buffer();
		$this->assertSame(1, $this->setPassword()(new Io($unknown), 'nobody'));
		$this->assertStringContainsString('No user with the login', $unknown->errorOutput());

		$weak = new Buffer("cli-weak-pass-1234\n");
		$this->assertSame(1, $this->setPassword()(new Io($weak), 'cli-weak-pass'));
		$this->assertStringContainsString('The password is too', $weak->errorOutput());
		$this->assertSame([], $this->logger->records);
	}

	private function setPassword(): SetPassword
	{
		return new SetPassword(
			$this->users,
			new PasswordPrompt(new PasswordPolicy($this->config(), $this->logger)),
			$this->logger,
		);
	}

	public function testPrintsALinkThatSignsTheUserInOnce(): void
	{
		$this->createTestUser(['username' => 'cli-link', 'email' => 'link@example.com']);
		$buffer = new Buffer();

		$this->assertSame(0, $this->loginLink()(new Io($buffer), 'cli-link', next: '/node/abc'));
		$token = preg_match('#/panel/handoff/([a-f0-9]{64})\?next=%2Fpanel%2Fnode%2Fabc#', $buffer->output(), $match)
		=== 1
			? $match[1]
			: '';

		$this->assertSame('cli-link', $this->users->byOneTimeToken($token)?->username);
		$this->assertNull($this->users->byOneTimeToken($token));
		$this->assertSame([['notice', 'Sign-in link for cli-link created from the console']], $this->logger->records);
	}

	public function testRefusesUsersWithoutPanelAccessAndPathsOutsideThePanel(): void
	{
		$this->createTestUser(['username' => 'cli-guest', 'role' => 'guest']);
		$this->createTestUser(['username' => 'cli-away']);

		$guest = new Buffer();
		$this->assertSame(1, $this->loginLink()(new Io($guest), 'cli-guest'));
		$this->assertStringContainsString('cli-guest has no panel access.', $guest->errorOutput());

		$away = new Buffer();
		$this->assertSame(1, $this->loginLink()(new Io($away), 'cli-away', next: '//example.com'));
		$this->assertStringContainsString('--next is a path inside the panel', $away->errorOutput());

		$unknown = new Buffer();
		$this->assertSame(1, $this->loginLink()(new Io($unknown), 'nobody'));
		$this->assertSame([], $this->logger->records);
	}

	private function loginLink(): LoginLink
	{
		return new LoginLink($this->users, Policy::withDefaults(), $this->config(), $this->logger);
	}

	public function testDeactivatesAndActivatesAUser(): void
	{
		$id = $this->createTestUser(['username' => 'cli-toggle']);
		$this->users->remember(hash('sha256', 'toggle'), $id, '2099-01-01 00:00:00');
		$this->assertNotNull($this->users->bySession(hash('sha256', 'toggle')));

		$off = new Buffer();
		$this->assertSame(0, (new DeactivateUser($this->users, $this->logger))(new Io($off), 'cli-toggle'));
		$this->assertNull($this->users->byLogin('cli-toggle'));
		$this->assertFalse($this->users->findByLogin('cli-toggle')->active ?? true);

		$again = new Buffer();
		$this->assertSame(0, (new DeactivateUser($this->users, $this->logger))(new Io($again), 'cli-toggle'));
		$this->assertStringContainsString('cli-toggle is already deactivated.', $again->output());

		$on = new Buffer();
		$this->assertSame(0, (new ActivateUser($this->users, $this->logger))(new Io($on), 'cli-toggle'));
		$this->assertTrue($this->users->byLogin('cli-toggle')->active ?? false);
		$this->assertNull($this->users->bySession(hash('sha256', 'toggle')));
		$this->assertSame(
			[
				['notice', 'User cli-toggle deactivated from the console'],
				['notice', 'User cli-toggle activated from the console'],
			],
			$this->logger->records,
		);
	}

	public function testKeepsTheLastActiveSuperuser(): void
	{
		$this->db()->execute("UPDATE cms.users SET active = false WHERE 'superuser' = ANY(roles)")->run();
		$this->createTestUser(['username' => 'cli-last', 'role' => 'superuser']);

		$buffer = new Buffer();
		$this->assertSame(1, (new DeactivateUser($this->users, $this->logger))(new Io($buffer), 'cli-last'));
		$this->assertStringContainsString('cli-last is the last active superuser', $buffer->errorOutput());
		$this->assertTrue($this->users->byLogin('cli-last')->active ?? false);
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
