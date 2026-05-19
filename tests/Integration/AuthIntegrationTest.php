<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Integration;

use Celemas\Cms\Auth;
use Celemas\Cms\Session;
use Celemas\Cms\Tests\IntegrationTestCase;
use Celemas\Cms\Users;
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
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types', 'sample-nodes');
	}

	private function createAuth(PsrServerRequest $request, ?Session $session = null): Auth
	{
		$config = $this->config([
			'app.secret' => 'test-secret-key-for-testing-only',
		]);

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

		$this->assertInstanceOf(\Celemas\Cms\User::class, $user);
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
		$session = new Session(['cache_expire' => 3600], 'test_session');
		$auth = $this->createAuth($request, $session);

		// Authenticate with remember me and session initialization
		$user = $auth->authenticate('remember@example.com', 'password', true, true);

		$this->assertInstanceOf(\Celemas\Cms\User::class, $user);

		// Verify session was created
		$sessionUserId = $session->authenticatedUserId();
		$this->assertEquals($userId, $sessionUserId);
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

		$this->assertInstanceOf(\Celemas\Cms\User::class, $user);
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

		$this->assertInstanceOf(\Celemas\Cms\User::class, $user);
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

		$this->assertInstanceOf(\Celemas\Cms\User::class, $user);
		$this->assertEquals($userId, $user->id);
	}
}
