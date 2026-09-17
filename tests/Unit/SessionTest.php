<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Session;
use Cosray\Tests\TestCase;
use Cosray\Token;
use Cosray\User;

/**
 * @internal
 *
 * @coversNothing
 */
final class SessionTest extends TestCase
{
	protected function tearDown(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			$_SESSION = [];
			session_unset();
			session_destroy();
		}

		parent::tearDown();
	}

	public function testAuthenticatedUserIdRoundTrip(): void
	{
		$session = new Session(['use_cookies' => 0], 'test-session');
		$session->start();
		$session->setUser($this->user('hash'));

		$this->assertSame(42, $session->authenticatedUserId());
	}

	public function testASessionStopsCountingOnceThePasswordChanged(): void
	{
		$session = new Session(['use_cookies' => 0], 'test-session');
		$session->start();
		$session->setUser($this->user('hash'));

		$this->assertTrue($session->holds($this->user('hash')));
		$this->assertFalse($session->holds($this->user('another hash')));
	}

	private function user(string $hash): User
	{
		return new User([
			'usr' => 42,
			'uid' => 'someone',
			'email' => 'someone@example.com',
			'password' => $hash,
			'active' => true,
			'created' => '2024-01-01T00:00:00+00:00',
			'changed' => '2024-01-01T00:00:00+00:00',
			'deleted' => null,
		]);
	}

	public function testSignalActivityPersistsTimestamp(): void
	{
		$session = new Session(['use_cookies' => 0], 'test-session');
		$session->start();
		$session->signalActivity();

		$this->assertIsInt($session->lastActivity());
		$this->assertGreaterThan(0, $session->lastActivity());
	}

	public function testAuthTokenCookieUsesDefaultName(): void
	{
		$session = new Session();
		$session->start();

		$_COOKIE['cosray_auth'] = 'token-value';

		$this->assertSame('token-value', $session->getAuthToken());
	}

	public function testRememberedCookieRoundTrip(): void
	{
		$session = new Session(['use_cookies' => 0], 'test-session');

		$session->remember(new Token('secret', 'token-value'), time() + 3600);

		$this->assertSame('token-value', $_COOKIE['test-session_auth']);
		$this->assertSame('token-value', $session->getAuthToken());

		$session->forgetRemembered();

		$this->assertArrayNotHasKey('test-session_auth', $_COOKIE);
		$this->assertNull($session->getAuthToken());
	}
}
