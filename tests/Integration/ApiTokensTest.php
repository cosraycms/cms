<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Console\Buffer;
use Celema\Console\Io;
use Cosray\Actor;
use Cosray\Commands\CreateToken;
use Cosray\Commands\ListTokens;
use Cosray\Commands\RevokeToken;
use Cosray\Tests\IntegrationTestCase;
use Cosray\User;
use Cosray\User\Tokens;
use Cosray\Users;
use DateTimeImmutable;
use ValueError;

final class ApiTokensTest extends IntegrationTestCase
{
	private Users $users;
	private Tokens $tokens;

	protected function setUp(): void
	{
		parent::setUp();

		$this->users = new Users($this->db());
		$this->tokens = new Tokens($this->db());
	}

	public function testATokenActsAsItsUserAndRecordsItsUse(): void
	{
		$user = $this->user('token-owner');
		$created = $this->tokens->create($user, 'Integration', Actor::system());

		$this->assertSame($user->uid, $this->users->byAuthToken($created['token'])?->uid);
		$listed = $this->tokens->list($user);
		$this->assertSame($created['uid'], $listed[0]['uid']);
		$this->assertSame('Integration', $listed[0]['name']);
		$this->assertNotNull($listed[0]['used']);
	}

	public function testAUserHoldsSeveralTokensAndRevokesOneOfThem(): void
	{
		$user = $this->user('token-many');
		$first = $this->tokens->create($user, 'Website', Actor::system());
		$second = $this->tokens->create($user, 'Importer', Actor::system());

		$this->assertTrue($this->tokens->revoke($user, $first['uid']));

		$this->assertNull($this->users->byAuthToken($first['token']));
		$this->assertSame($user->uid, $this->users->byAuthToken($second['token'])?->uid);
		$this->assertFalse($this->tokens->revoke($user, $first['uid']));
	}

	public function testOnlyTheOwnerRevokesAToken(): void
	{
		$owner = $this->user('token-owner-b');
		$other = $this->user('token-other');
		$created = $this->tokens->create($owner, 'Website', Actor::system());

		$this->assertFalse($this->tokens->revoke($other, $created['uid']));
		$this->assertNotNull($this->users->byAuthToken($created['token']));
	}

	public function testAnExpiredTokenNoLongerAuthenticates(): void
	{
		$user = $this->user('token-expiring');
		$created = $this->tokens->create($user, 'Short', Actor::system(), new DateTimeImmutable('+1 hour'));
		$this->db()->execute(
			"UPDATE cms.auth_tokens SET expires = now() - interval '1 second' WHERE uid = :uid",
			['uid' => $created['uid']],
		)->run();

		$this->assertNull($this->users->byAuthToken($created['token']));
	}

	public function testATokenNeedsANameAndAFutureExpiry(): void
	{
		$user = $this->user('token-invalid');

		$this->assertRejected(fn() => $this->tokens->create($user, '  ', Actor::system()));
		$this->assertRejected(fn() => $this->tokens->create(
			$user,
			'Past',
			Actor::system(),
			new DateTimeImmutable('-1 minute'),
		));
	}

	public function testTheCommandsCreateListAndRevokeTokens(): void
	{
		$this->user('token-cli');

		$create = new Buffer();
		$exit = (new CreateToken($this->users, $this->tokens))(new Io($create), 'token-cli', 'Importer', '2099-01-31');
		$this->assertSame(0, $exit);
		$lines = explode("\n", trim($create->output()));
		$token = end($lines);
		$this->assertSame('token-cli', $this->users->byAuthToken($token)?->username);

		$list = new Buffer();
		$this->assertSame(0, (new ListTokens($this->users, $this->tokens))(new Io($list), 'token-cli'));
		$this->assertStringContainsString('Importer', $list->output());
		$uid = $this->tokens->list($this->users->byLogin('token-cli'))[0]['uid'];

		$revoke = new Buffer();
		$this->assertSame(0, (new RevokeToken($this->users, $this->tokens))(new Io($revoke), 'token-cli', $uid));
		$this->assertNull($this->users->byAuthToken($token));
	}

	public function testTheCommandsRefuseUnknownUsersAndBadInput(): void
	{
		$this->user('token-cli-bad');
		$create = new CreateToken($this->users, $this->tokens);

		$unknown = new Buffer();
		$this->assertSame(1, $create(new Io($unknown), 'nobody', 'Importer'));
		$this->assertStringContainsString('No active user', $unknown->errorOutput());

		$expiry = new Buffer();
		$this->assertSame(1, $create(new Io($expiry), 'token-cli-bad', 'Importer', 'not a date'));
		$this->assertStringContainsString('Cannot read the expiry', $expiry->errorOutput());

		$name = new Buffer();
		$this->assertSame(1, $create(new Io($name), 'token-cli-bad'));
		$this->assertStringContainsString('needs a name', $name->errorOutput());

		$revoke = new Buffer();
		$this->assertSame(1, (new RevokeToken($this->users, $this->tokens))(
			new Io($revoke),
			'token-cli-bad',
			'missing',
		));

		$empty = new Buffer();
		$this->assertSame(0, (new ListTokens($this->users, $this->tokens))(new Io($empty), 'token-cli-bad'));
		$this->assertStringContainsString('has no API tokens', $empty->output());
	}

	private function user(string $username): User
	{
		$this->createTestUser(['uid' => $username, 'username' => $username]);

		return $this->users->byLogin($username) ?? $this->fail("Missing user {$username}");
	}

	private function assertRejected(callable $create): void
	{
		try {
			$create();
			$this->fail('The token was created');
		} catch (ValueError) {
			$this->addToAssertionCount(1);
		}
	}
}
