<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;

/**
 * End-to-end tests for panel authentication and login flow.
 *
 * @internal
 *
 * @coversNothing
 */
final class PanelAuthTest extends End2EndTestCase
{
	public function testProtectedPanelRouteRedirectsGuestToLogin(): void
	{
		$response = $this->makeRequest('GET', '/cp');

		$this->assertResponseStatus(303, $response);
		$this->assertSame('/cp/login?next=%2Fcp', $response->getHeaderLine('Location'));
	}

	public function testLoginPageRendersForGuest(): void
	{
		$response = $this->makeRequest('GET', '/cp/login');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('<h1>Login</h1>', $html);
		$this->assertStringContainsString('action="/cp/login"', $html);
	}

	public function testLoginWithValidCredentialsRedirectsToPanel(): void
	{
		$login = 'panel-login-user';
		$userId = $this->createTestUser([
			'uid' => 'panel-login-user',
			'username' => $login,
			'email' => 'panel-login@example.com',
			'password' => password_hash('password', PASSWORD_ARGON2ID),
		]);
		$this->createdUserIds[] = $userId;

		$response = $this->makeRequest('POST', '/cp/login', [
			'body' => [
				'login' => $login,
				'password' => 'password',
				'rememberme' => false,
				'next' => '/cp',
			],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertSame('/cp', $response->getHeaderLine('Location'));
	}

	public function testLoginWithInvalidCredentialsShowsMessage(): void
	{
		$response = $this->makeRequest('POST', '/cp/login', [
			'body' => [
				'login' => 'nobody@example.com',
				'password' => 'wrong-password',
				'rememberme' => false,
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('Invalid username or password', $html);
	}

	public function testAuthenticatedPanelUserGetsRedirectedAwayFromLogin(): void
	{
		$this->authenticateAs('editor');

		$response = $this->makeRequest('GET', '/cp/login', [
			'authToken' => $this->defaultAuthToken,
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertSame('/cp', $response->getHeaderLine('Location'));
	}

	public function testAuthenticatedPanelRendersSidebarLayout(): void
	{
		$this->authenticateAs('editor');

		$response = $this->makeRequest('GET', '/cp', [
			'authToken' => $this->defaultAuthToken,
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('class="panel"', $html);
		$this->assertStringContainsString('class="cms-sidebar"', $html);
		$this->assertStringContainsString('class="main"', $html);
		$this->assertStringContainsString('class="logo"', $html);
		$this->assertStringContainsString('action="/cp/logout"', $html);
		$this->assertStringContainsString('Dashboard', $html);
	}

	public function testHtmxGuestRequestReturnsHxRedirectHeader(): void
	{
		$response = $this->makeRequest('GET', '/cp', [
			'headers' => ['HX-Request' => 'true'],
		]);

		$this->assertResponseStatus(401, $response);
		$this->assertSame('/cp/login?next=%2Fcp', $response->getHeaderLine('HX-Redirect'));
	}

	public function testConfiguredPanelPathAppliesOnlyToNewPanel(): void
	{
		$this->app = $this->createApp(['path.panel' => '/admin']);

		$newPanelResponse = $this->makeRequest('GET', '/admin');
		$legacyBootResponse = $this->makeRequest('GET', '/panel/boot');

		$this->assertResponseStatus(303, $newPanelResponse);
		$this->assertSame('/admin/login?next=%2Fadmin', $newPanelResponse->getHeaderLine('Location'));
		$this->assertJsonResponse($legacyBootResponse, 200);
	}

	public function testUserWithoutPanelPermissionGetsRedirectedToLogin(): void
	{
		$token = $this->createAuthenticatedUser('system');

		$response = $this->makeRequest('GET', '/cp', [
			'authToken' => $token,
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertSame('/cp/login?next=%2Fcp', $response->getHeaderLine('Location'));
	}
}
