<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Session;
use Cosray\Tests\TestCase;

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
		$session->setUser(42);

		$this->assertSame(42, $session->authenticatedUserId());
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
}
