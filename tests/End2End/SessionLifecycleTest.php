<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Celema\Core\Factory\Factory;
use Celema\Core\Response;
use Cosray\Tests\End2EndTestCase;

/**
 * Sessions across several requests of one app instance, as in a worker.
 * Isolation between different clients needs real cookies and is checked
 * against a running worker; native sessions read `$_COOKIE`, which
 * in-process requests do not set.
 *
 * @internal
 *
 * @coversNothing
 */
final class SessionLifecycleTest extends End2EndTestCase
{
	public function testLoginLastsUntilLogoutEndsTheSession(): void
	{
		$this->createTestUser([
			'uid' => 'session-lifecycle-user',
			'username' => 'session-lifecycle-user',
			'email' => 'session-lifecycle@example.com',
			'password' => self::passwordHash(),
		]);

		$login = $this->makeRequest('POST', '/panel/login', [
			'body' => ['login' => 'session-lifecycle-user', 'password' => 'password', 'next' => '/panel'],
		]);
		$this->assertResponseStatus(303, $login);
		$this->assertResponseOk($this->makeRequest('GET', '/panel'));

		$this->makeRequest('POST', '/panel/logout');

		$afterLogout = $this->makeRequest('GET', '/panel');
		$this->assertResponseStatus(303, $afterLogout);
		$this->assertStringStartsWith('/panel/login', $afterLogout->getHeaderLine('Location'));
	}

	public function testRequestWithoutSessionFindsNoOpenSession(): void
	{
		$this->app->patch('/probe/session', static fn(Factory $factory): Response => Response::create($factory)->text(
			session_status() === PHP_SESSION_ACTIVE ? 'open' : 'closed',
		));

		$this->assertResponseOk($this->makeRequest('GET', '/panel/login'));

		$this->assertSame('closed', (string) $this->makeRequest('PATCH', '/probe/session')->getBody());
	}
}
