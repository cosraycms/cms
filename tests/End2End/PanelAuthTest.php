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
		$this->assertStringContainsString('Sign in to your account', $html);
		$this->assertStringContainsString('action="/cp/login"', $html);
		$this->assertStringContainsString('Forgot password?', $html);
		$this->assertStringContainsString('"code:syntax":"Syntax"', $html);
	}

	public function testLoginPageUsesNegotiatedLanguage(): void
	{
		$response = $this->makeRequest('GET', '/cp/login', [
			'headers' => ['Accept-Language' => 'de'],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('<html lang="de">', $html);
		$this->assertStringContainsString('Bei Ihrem Konto anmelden', $html);
		$this->assertStringContainsString('Passwort vergessen?', $html);
	}

	public function testLoginWithValidCredentialsRedirectsToPanel(): void
	{
		$login = 'panel-login-user';
		$this->createTestUser([
			'uid' => 'panel-login-user',
			'username' => $login,
			'email' => 'panel-login@example.com',
			'password' => password_hash('password', PASSWORD_ARGON2ID),
		]);

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
		$this->assertStringContainsString('class="cms-shell"', $html);
		$this->assertStringContainsString('class="cms-masthead"', $html);
		$this->assertStringContainsString('class="main"', $html);
		// The rail carries collections, and this app registers none, so it is
		// left out rather than rendered empty.
		$this->assertStringNotContainsString('class="cms-sidebar"', $html);
		$this->assertStringContainsString('class="logo"', $html);
		$this->assertStringContainsString('action="/cp/logout"', $html);
		// Dashboard and media are areas in the masthead, not rail entries.
		$this->assertStringContainsString('class="areas"', $html);
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

	public function testConfiguredPanelPathApplies(): void
	{
		$this->app = $this->createApp(['path.panel' => '/admin']);

		$response = $this->makeRequest('GET', '/admin');

		$this->assertResponseStatus(303, $response);
		$this->assertSame('/admin/login?next=%2Fadmin', $response->getHeaderLine('Location'));
	}

	/**
	 * Element control modules resolve against this base. A boosted navigation
	 * upgrades the custom elements in the swapped markup while inserting them,
	 * before any swap handler could read the editor payload, so the base has to
	 * be in the document from the start or the modules resolve against the
	 * built-in default and 404 on every panel not mounted at /panel.
	 */
	public function testDocumentCarriesThePanelBaseForElementModules(): void
	{
		$this->app = $this->createApp(['path.panel' => '/admin']);
		$token = $this->createAuthenticatedUser('editor');

		$response = $this->makeRequest('GET', '/admin', ['authToken' => $token]);

		$this->assertResponseOk($response);
		$this->assertStringContainsString(
			'window.COSRAY_BASE_PATH = "/admin/";',
			$this->getHtmlResponse($response),
		);
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
