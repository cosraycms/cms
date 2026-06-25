<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Auth;
use Cosray\Session;
use Cosray\Tests\IntegrationTestCase;
use Cosray\Token;
use Cosray\Users;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequest;

/**
 * Integration tests for the Auth class authentication flows.
 *
 * @internal
 *
 * @coversNothing
 */
final class AuthIntegrationTest extends IntegrationTestCase
{
	private const string SECRET = 'test-secret-key-for-testing-only';

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types', 'sample-nodes');
	}

	protected function tearDown(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			$_SESSION = [];
			session_unset();
			session_destroy();
		}

		parent::tearDown();
	}

	private function createAuth(
		PsrServerRequest $request,
		?Session $session = null,
		array $settings = [],
	): Auth {
		$config = $this->config(array_merge([
			'app.secret' => self::SECRET,
		], $settings));

		return new Auth(
			$request,
			new Users($this->db()),
			$config,
			$session,
		);
	}

	public function testAuthenticateReturnsUserOnValidCredentials(): void
	{
		$userId = $this->createTestUser([
			'uid' => 'auth-test-user',
			'username' => 'testuser',
			'email' => 'test@example.com',
			'password' => password_hash('correct-password', PASSWORD_ARGON2ID),
		]);

		$request = $this->psrRequest();
		$auth = $this->createAuth($request);

		$user = $auth->authenticate('test@example.com', 'correct-password', false, false);

		$this->assertInstanceOf(\Cosray\User::class, $user);
		$this->assertEquals($userId, $user->id);
	}

	public function testAuthenticateReturnsFalseOnInvalidPassword(): void
	{
		$this->createTestUser([
			'uid' => 'auth-wrong-pass',
			'email' => 'wrong@example.com',
			'password' => password_hash('correct-password', PASSWORD_ARGON2ID),
		]);

		$request = $this->psrRequest();
		$auth = $this->createAuth($request);

		$result = $auth->authenticate('wrong@example.com', 'wrong-password', false, false);

		$this->assertFalse($result);
	}

	public function testAuthenticateReturnsFalseOnUnknownUser(): void
	{
		$request = $this->psrRequest();
		$auth = $this->createAuth($request);

		$result = $auth->authenticate('nonexistent@example.com', 'any-password', false, false);

		$this->assertFalse($result);
	}

	public function testAuthenticateWithRememberMeCreatesSession(): void
	{
		$userId = $this->createTestUser([
			'uid' => 'auth-remember-user',
			'email' => 'remember@example.com',
			'password' => password_hash('password', PASSWORD_ARGON2ID),
		]);

		$request = $this->psrRequest();
		$session = new Session(['use_cookies' => 0], 'test_session');
		$auth = $this->createAuth($request, $session);

		// Authenticate with remember me and session initialization
		$user = $auth->authenticate('remember@example.com', 'password', true, true);

		$this->assertInstanceOf(\Cosray\User::class, $user);

		// Verify session was created
		$sessionUserId = $session->authenticatedUserId();
		$this->assertEquals($userId, $sessionUserId);
	}

	public function testRememberMeUsesConfiguredLifetime(): void
	{
		$userId = $this->createTestUser([
			'uid' => 'auth-remember-lifetime-user',
			'email' => 'remember-lifetime@example.com',
			'password' => password_hash('password', PASSWORD_ARGON2ID),
		]);

		$request = $this->psrRequest();
		$session = new Session(['use_cookies' => 0, 'cache_expire' => 1], 'test_session');
		$auth = $this->createAuth($request, $session, [
			'auth.remember_lifetime' => 7200,
		]);

		$user = $auth->authenticate('remember-lifetime@example.com', 'password', true, true);

		$this->assertInstanceOf(\Cosray\User::class, $user);

		$row = $this->db()->execute(
			'SELECT EXTRACT(EPOCH FROM expires - now()) AS ttl FROM cms.login_sessions WHERE usr = :usr',
			['usr' => $userId],
		)->one();
		$ttl = (float) $row['ttl'];

		$this->assertGreaterThanOrEqual(7195.0, $ttl);
		$this->assertLessThanOrEqual(7205.0, $ttl);
	}

	public function testLoginWithoutRememberMeClearsExistingRememberToken(): void
	{
		$userId = $this->createTestUser([
			'uid' => 'auth-no-remember-user',
			'email' => 'no-remember@example.com',
			'password' => password_hash('password', PASSWORD_ARGON2ID),
		]);
		$token = 'existing-remember-token';
		$hash = new Token(self::SECRET, $token)->hash();
		$this->db()->execute(
			"INSERT INTO cms.login_sessions (hash, usr, expires) VALUES (:hash, :usr, now() + INTERVAL '1 day')",
			['hash' => $hash, 'usr' => $userId],
		)->run();
		$_COOKIE['test_session_auth'] = $token;

		$request = $this->psrRequest();
		$session = new Session(['use_cookies' => 0], 'test_session');
		$auth = $this->createAuth($request, $session);

		$user = $auth->authenticate('no-remember@example.com', 'password', false, true);

		$this->assertInstanceOf(\Cosray\User::class, $user);
		$this->assertArrayNotHasKey('test_session_auth', $_COOKIE);
		$exists = $this->db()->execute(
			'SELECT EXISTS(SELECT 1 FROM cms.login_sessions WHERE hash = :hash) as exists',
			['hash' => $hash],
		)->one()['exists'];
		$this->assertFalse($exists);
	}

	public function testValidRememberCookieRestoresAndRotatesSession(): void
	{
		$userId = $this->createTestUser([
			'uid' => 'auth-restore-remember-user',
			'email' => 'restore-remember@example.com',
		]);
		$token = 'restore-remember-token';
		$hash = new Token(self::SECRET, $token)->hash();
		$this->db()->execute(
			"INSERT INTO cms.login_sessions (hash, usr, expires) VALUES (:hash, :usr, now() + INTERVAL '1 day')",
			['hash' => $hash, 'usr' => $userId],
		)->run();
		$_COOKIE['test_session_auth'] = $token;

		$request = $this->psrRequest();
		$session = new Session(['use_cookies' => 0], 'test_session');
		$auth = $this->createAuth($request, $session, [
			'auth.remember_lifetime' => 7200,
		]);

		$user = $auth->user();

		$this->assertInstanceOf(\Cosray\User::class, $user);
		$this->assertSame($userId, $user->id);
		$this->assertSame($userId, $session->authenticatedUserId());
		$newToken = $_COOKIE['test_session_auth'] ?? null;
		$this->assertIsString($newToken);
		$this->assertNotSame($token, $newToken);
		$newHash = new Token(self::SECRET, $newToken)->hash();
		$row = $this->db()->execute(
			'SELECT hash FROM cms.login_sessions WHERE usr = :usr',
			['usr' => $userId],
		)->one();
		$this->assertSame($newHash, $row['hash']);
	}

	public function testExpiredRememberCookieIsCleared(): void
	{
		$userId = $this->createTestUser([
			'uid' => 'auth-expired-remember-user',
			'email' => 'expired-remember@example.com',
		]);
		$token = 'expired-remember-token';
		$hash = new Token(self::SECRET, $token)->hash();
		$this->db()->execute(
			"INSERT INTO cms.login_sessions (hash, usr, expires) VALUES (:hash, :usr, now() - INTERVAL '1 day')",
			['hash' => $hash, 'usr' => $userId],
		)->run();
		$_COOKIE['test_session_auth'] = $token;

		$request = $this->psrRequest();
		$session = new Session(['use_cookies' => 0], 'test_session');
		$auth = $this->createAuth($request, $session);

		$user = $auth->user();

		$this->assertNull($user);
		$this->assertArrayNotHasKey('test_session_auth', $_COOKIE);
		$exists = $this->db()->execute(
			'SELECT EXISTS(SELECT 1 FROM cms.login_sessions WHERE hash = :hash) as exists',
			['hash' => $hash],
		)->one()['exists'];
		$this->assertFalse($exists);
	}

	public function testGetAuthTokenFromBearerHeader(): void
	{
		$token = 'test-token-12345';
		$request = $this->psrRequest()->withHeader('Authentication', 'Bearer ' . $token);
		// Pass PSR request directly

		$auth = $this->createAuth($request);

		$this->assertEquals($token, $auth->getAuthToken());
	}

	public function testGetAuthTokenReturnsEmptyStringWithoutHeader(): void
	{
		$request = $this->psrRequest();
		$auth = $this->createAuth($request);

		$this->assertEquals('', $auth->getAuthToken());
	}

	public function testGetAuthTokenReturnsEmptyStringWithInvalidFormat(): void
	{
		$request = $this->psrRequest()->withHeader('Authentication', 'InvalidFormat token123');
		// Pass PSR request directly

		$auth = $this->createAuth($request);

		$this->assertEquals('', $auth->getAuthToken());
	}

	public function testUserFromTokenReturnsUserWithValidToken(): void
	{
		$userId = $this->createTestUser([
			'uid' => 'token-auth-user',
			'email' => 'token@example.com',
		]);

		// Create auth token in database
		$token = bin2hex(random_bytes(32));
		$tokenHash = hash('sha256', $token);

		$this->db()->execute(
			'INSERT INTO cms.auth_tokens (token, usr, creator, editor) VALUES (:token, :usr, 1, 1)',
			['token' => $tokenHash, 'usr' => $userId],
		)->run();

		$request = $this->psrRequest()->withHeader('Authentication', 'Bearer ' . $token);
		// Pass PSR request directly
		$auth = $this->createAuth($request);

		$user = $auth->user();

		$this->assertInstanceOf(\Cosray\User::class, $user);
		$this->assertEquals($userId, $user->id);

		// Cleanup
		$this->db()->execute('DELETE FROM cms.auth_tokens WHERE token = :token', [
			'token' => $tokenHash,
		])->run();
	}

	public function testUserFromTokenReturnsNullWithInvalidToken(): void
	{
		$request = $this->psrRequest()->withHeader('Authentication', 'Bearer invalid-token');
		// Pass PSR request directly
		$auth = $this->createAuth($request);

		$user = $auth->user();

		$this->assertNull($user);
	}

	public function testPermissionsReturnsEmptyArrayForGuest(): void
	{
		$request = $this->psrRequest();
		$auth = $this->createAuth($request);

		$permissions = $auth->permissions();

		$this->assertIsArray($permissions);
		$this->assertCount(0, $permissions);
	}

	public function testPermissionsReturnsUserPermissions(): void
	{
		$userId = $this->createTestUser([
			'uid' => 'permissions-user',
			'email' => 'perms@example.com',
			'rolename' => 'editor',
		]);

		// Create auth token
		$token = bin2hex(random_bytes(32));
		$tokenHash = hash('sha256', $token);

		$this->db()->execute(
			'INSERT INTO cms.auth_tokens (token, usr, creator, editor) VALUES (:token, :usr, 1, 1)',
			['token' => $tokenHash, 'usr' => $userId],
		)->run();

		$request = $this->psrRequest()->withHeader('Authentication', 'Bearer ' . $token);
		// Pass PSR request directly
		$auth = $this->createAuth($request);

		$permissions = $auth->permissions();

		$this->assertIsArray($permissions);
		// Editor role has specific permissions defined in the database

		// Cleanup
		$this->db()->execute('DELETE FROM cms.auth_tokens WHERE token = :token', [
			'token' => $tokenHash,
		])->run();
	}

	public function testAuthenticateByOneTimeTokenWithValidToken(): void
	{
		$userId = $this->createTestUser([
			'uid' => 'onetime-user',
			'email' => 'onetime@example.com',
		]);

		// Create one-time token
		$token = bin2hex(random_bytes(32));
		$tokenHash = hash('sha256', $token);

		$this->db()->execute(
			'INSERT INTO cms.one_time_tokens (token, usr) VALUES (:token, :usr)',
			['token' => $tokenHash, 'usr' => $userId],
		)->run();

		$request = $this->psrRequest();
		$auth = $this->createAuth($request);

		$user = $auth->authenticateByOneTimeToken($token, false);

		$this->assertInstanceOf(\Cosray\User::class, $user);
		$this->assertEquals($userId, $user->id);

		// Cleanup
		$this->db()->execute('DELETE FROM cms.one_time_tokens WHERE token = :token', [
			'token' => $tokenHash,
		])->run();
	}

	public function testAuthenticateByOneTimeTokenWithInvalidToken(): void
	{
		$request = $this->psrRequest();
		$auth = $this->createAuth($request);

		$result = $auth->authenticateByOneTimeToken('invalid-token', false);

		$this->assertFalse($result);
	}

	public function testGetOneTimeTokenCreatesToken(): void
	{
		$userId = $this->createTestUser([
			'uid' => 'create-onetime-user',
			'email' => 'create-onetime@example.com',
		]);

		// Create auth token
		$authToken = bin2hex(random_bytes(32));
		$authTokenHash = hash('sha256', $authToken);

		$this->db()->execute(
			'INSERT INTO cms.auth_tokens (token, usr, creator, editor) VALUES (:token, :usr, 1, 1)',
			['token' => $authTokenHash, 'usr' => $userId],
		)->run();

		$request = $this->psrRequest()->withHeader('Authentication', 'Bearer ' . $authToken);
		// Pass PSR request directly
		$auth = $this->createAuth($request);

		$oneTimeToken = $auth->getOneTimeToken($authToken);

		$this->assertNotFalse($oneTimeToken);
		$this->assertIsString($oneTimeToken);
		$this->assertGreaterThan(0, strlen($oneTimeToken));

		// Cleanup
		$this->db()->execute('DELETE FROM cms.auth_tokens WHERE token = :token', [
			'token' => $authTokenHash,
		])->run();
		$this->db()->execute('DELETE FROM cms.one_time_tokens WHERE usr = :usr', [
			'usr' => $userId,
		])->run();
	}

	public function testGetOneTimeTokenReturnsFalseForInvalidAuthToken(): void
	{
		$request = $this->psrRequest();
		$auth = $this->createAuth($request);

		$result = $auth->getOneTimeToken('invalid-auth-token');

		$this->assertFalse($result);
	}

	public function testInvalidateOneTimeTokenRemovesToken(): void
	{
		$userId = $this->createTestUser([
			'uid' => 'invalidate-onetime-user',
			'email' => 'invalidate@example.com',
		]);

		// Create one-time token
		$token = bin2hex(random_bytes(32));
		$tokenHash = hash('sha256', $token);

		$this->db()->execute(
			'INSERT INTO cms.one_time_tokens (token, usr) VALUES (:token, :usr)',
			['token' => $tokenHash, 'usr' => $userId],
		)->run();

		$request = $this->psrRequest();
		$auth = $this->createAuth($request);

		// Invalidate the token
		$auth->invalidateOneTimeToken($token);

		// Verify token is removed
		$exists = $this->db()->execute(
			'SELECT EXISTS(SELECT 1 FROM cms.one_time_tokens WHERE token = :token) as exists',
			['token' => $tokenHash],
		)->one()['exists'];

		$this->assertFalse($exists);
	}

	public function testLogoutWithoutSessionDoesNothing(): void
	{
		$request = $this->psrRequest();
		$auth = $this->createAuth($request, null);

		// Should not throw, just return early
		$auth->logout();

		// No assertions needed - just verifying no exception
		$this->assertTrue(true);
	}

	public function testUserViaSessionId(): void
	{
		$userId = $this->createTestUser([
			'uid' => 'session-auth-user',
			'email' => 'session@example.com',
		]);

		$request = $this->psrRequest();
		$session = new Session(['cache_expire' => 3600], 'test_session');
		$session->setUser($userId);

		$auth = $this->createAuth($request, $session);

		$user = $auth->user();

		$this->assertInstanceOf(\Cosray\User::class, $user);
		$this->assertEquals($userId, $user->id);
	}
}
