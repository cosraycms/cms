<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;
use Cosray\Users;
use Psr\Http\Message\ResponseInterface;

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
		$response = $this->makeRequest('GET', '/panel');

		$this->assertResponseStatus(303, $response);
		$this->assertSame('/panel/login?next=%2Fpanel', $response->getHeaderLine('Location'));
	}

	public function testLoginPageRendersForGuest(): void
	{
		$response = $this->makeRequest('GET', '/panel/login');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('Sign in to your account', $html);
		$this->assertStringContainsString('action="/panel/login"', $html);
		$this->assertStringContainsString('"code:syntax":"Syntax"', $html);
	}

	public function testLoginPageUsesNegotiatedLanguage(): void
	{
		$response = $this->makeRequest('GET', '/panel/login', [
			'headers' => ['Accept-Language' => 'de'],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('<html lang="de">', $html);
		$this->assertStringContainsString('Bei Ihrem Konto anmelden', $html);
		$this->assertStringContainsString('Angemeldet bleiben', $html);
	}

	public function testLoginWithValidCredentialsRedirectsToPanel(): void
	{
		$login = 'panel-login-user';
		$this->createTestUser([
			'uid' => 'panel-login-user',
			'username' => $login,
			'email' => 'panel-login@example.com',
			'password' => self::passwordHash(),
		]);

		$response = $this->makeRequest('POST', '/panel/login', [
			'body' => [
				'login' => $login,
				'password' => 'password',
				'rememberme' => false,
				'next' => '/panel',
			],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertSame('/panel', $response->getHeaderLine('Location'));
	}

	public function testLoginWithInvalidCredentialsShowsMessage(): void
	{
		$response = $this->makeRequest('POST', '/panel/login', [
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

		$response = $this->makeRequest('GET', '/panel/login', [
			'authToken' => $this->defaultAuthToken,
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertSame('/panel', $response->getHeaderLine('Location'));
	}

	public function testAuthenticatedPanelRendersSidebarLayout(): void
	{
		$this->authenticateAs('editor');

		$response = $this->makeRequest('GET', '/panel', [
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
		$this->assertStringContainsString('action="/panel/logout"', $html);
		// A user without a stored name is identified by their login.
		$this->assertMatchesRegularExpression(
			'/popovertarget="cms-account-menu"[^>]*aria-label="Account: test-auth-[^"]+@example\.com"/',
			$html,
		);
		// Dashboard and media are areas in the masthead, not rail entries.
		$this->assertStringContainsString('class="areas"', $html);
		$this->assertStringContainsString('Dashboard', $html);
	}

	public function testHtmxGuestRequestReturnsHxRedirectHeader(): void
	{
		$response = $this->makeRequest('GET', '/panel', [
			'headers' => ['HX-Request' => 'true'],
		]);

		$this->assertResponseStatus(401, $response);
		$this->assertSame('/panel/login?next=%2Fpanel', $response->getHeaderLine('HX-Redirect'));
	}

	public function testConfiguredPanelPathApplies(): void
	{
		$this->app = $this->createApp(['panel.path' => '/admin']);

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
		$this->app = $this->createApp(['panel.path' => '/admin']);
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

		$response = $this->makeRequest('GET', '/panel', [
			'authToken' => $token,
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertSame('/panel/login?next=%2Fpanel', $response->getHeaderLine('Location'));
	}

	public function testRefusedPanelLoginIsLogged(): void
	{
		$this->createTestUser([
			'uid' => 'panel-refused-user',
			'username' => 'refused',
			'role' => 'system',
		]);

		$this->makeRequest('POST', '/panel/login', [
			'body' => ['login' => 'refused', 'password' => 'password', 'rememberme' => false],
			'server' => ['REMOTE_ADDR' => '192.0.2.1'],
		]);

		$this->assertContains(
			['notice', 'Panel login refused for refused from 192.0.2.1: no panel permission'],
			$this->logger->records,
		);
	}

	public function testLogoutIsLogged(): void
	{
		$this->createTestUser(['uid' => 'panel-logout-user', 'username' => 'leaving']);
		$this->makeRequest('POST', '/panel/login', [
			'body' => ['login' => 'leaving', 'password' => 'password', 'rememberme' => false],
		]);

		$this->makeRequest('POST', '/panel/logout', [
			'server' => ['REMOTE_ADDR' => '192.0.2.1'],
		]);

		$this->assertContains(['info', 'Logout of leaving from 192.0.2.1'], $this->logger->records);
	}

	public function testRepeatedFailuresForALoginAreRefusedForAWhile(): void
	{
		$this->createTestUser(['uid' => 'panel-guessed-user', 'username' => 'guessed']);

		for ($i = 0; $i < 5; $i++) {
			$this->assertResponseOk($this->signIn('guessed', 'wrong-password'));
		}

		$refused = $this->signIn('GUESSED', 'password');
		$this->assertResponseStatus(429, $refused);
		$this->assertSame('900', $refused->getHeaderLine('Retry-After'));
		$this->assertStringContainsString(
			'Too many attempts. Please try again in 15 minutes.',
			$this->getHtmlResponse($refused),
		);
		$this->assertContains(
			['warning', 'Login from 192.0.2.7 refused: too many failed attempts'],
			$this->logger->records,
		);
		$this->assertResponseStatus(303, $this->signIn('guessed', 'password', '198.51.100.1'));
	}

	public function testASuccessfulSignInClearsTheFailuresOfItsLogin(): void
	{
		$this->createTestUser(['uid' => 'panel-careless-user', 'username' => 'careless']);

		foreach ([4, 4] as $failures) {
			for ($i = 0; $i < $failures; $i++) {
				$this->signIn('careless', 'wrong-password');
			}

			$this->assertResponseStatus(303, $this->signIn('careless', 'password'));
		}
	}

	public function testManyFailuresFromOneAddressRefuseEveryLogin(): void
	{
		for ($user = 0; $user < 6; $user++) {
			$this->createTestUser(['uid' => "panel-sprayed-{$user}", 'username' => "sprayed-{$user}"]);

			for ($i = 0; $i < 5; $i++) {
				$this->signIn("sprayed-{$user}", 'wrong-password');
			}
		}

		$id = $this->createTestUser(['uid' => 'panel-sprayed-user', 'username' => 'sprayed']);
		$this->assertResponseStatus(429, $this->signIn('sprayed', 'password'));

		$token = new Users($this->db())->createOneTimeToken($id);
		$handoff = $this->makeRequest('GET', '/panel/handoff/' . $token, [
			'authToken' => '',
			'server' => ['REMOTE_ADDR' => '192.0.2.7'],
		]);
		$this->assertSame('/panel/login', $handoff->getHeaderLine('Location'));
	}

	private function signIn(
		string $login,
		#[\SensitiveParameter]
		string $password,
		string $ip = '192.0.2.7',
	): ResponseInterface {
		return $this->makeRequest('POST', '/panel/login', [
			'body' => ['login' => $login, 'password' => $password, 'rememberme' => false, 'next' => '/panel'],
			'server' => ['REMOTE_ADDR' => $ip],
		]);
	}
}
