<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;
use Cosray\Users;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class PanelCsrfTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->createTestUser(['uid' => 'csrf-user', 'username' => 'csrf-user']);
	}

	public function testAPlainFormWithoutTheTokenLeadsToAPageThatLinksBack(): void
	{
		$this->signIn();

		$response = $this->switchLanguage('de', [
			'csrf' => false,
			'headers' => ['Referer' => 'http://localhost/panel/node/some-page?tab=meta'],
		]);

		$this->assertResponseStatus(303, $response);
		$location = $response->getHeaderLine('Location');
		$this->assertSame(
			'/panel/expired?' . http_build_query(['next' => '/panel/node/some-page?tab=meta']),
			$location,
		);
		$this->assertNull($this->panelLocale());
		$this->assertStringContainsString(
			'href="/panel/node/some-page?tab=meta"',
			$this->getHtmlResponse($this->makeRequest('GET', $location)),
		);
	}

	public function testTheExpiredPageLinksOnlyIntoThePanel(): void
	{
		$html = $this->getHtmlResponse($this->makeRequest('GET', '/panel/expired', [
			'query' => ['next' => 'https://example.com/panel'],
		]));

		$this->assertStringContainsString('href="/panel"', $html);
		$this->assertStringNotContainsString('example.com', $html);
	}

	public function testScriptedRequestsLearnOfTheRefusalWithoutASwap(): void
	{
		$this->signIn();

		$htmx = $this->switchLanguage('de', ['csrf' => false, 'headers' => ['HX-Request' => 'true']]);
		$fetch = $this->switchLanguage('de', [
			'csrf' => false,
			'headers' => ['X-CSRF-Token' => 'outdated', 'Accept' => 'application/json'],
		]);

		$this->assertResponseStatus(403, $htmx);
		$this->assertSame('cosray:csrf-expired', $htmx->getHeaderLine('HX-Trigger'));
		$this->assertSame('none', $htmx->getHeaderLine('HX-Reswap'));
		$this->assertResponseStatus(403, $fetch);
		$this->assertFalse(json_decode((string) $fetch->getBody(), true)['ok']);
		$this->assertNull($this->panelLocale());
		$this->assertContains(
			['warning', 'Refused POST /panel/locale from 192.0.2.7: missing or invalid CSRF token'],
			$this->logger->records,
		);
	}

	public function testTheTokenComesAsHeaderOrFormField(): void
	{
		$this->signIn();

		$this->switchLanguage('de');
		$this->assertSame('de', $this->panelLocale());

		$this->switchLanguage('en', ['csrf' => false, 'form' => ['locale' => 'en', '_token' => $this->csrfToken()]]);
		$this->assertSame('en', $this->panelLocale());
	}

	public function testSigningInNeedsTheTokenAndReplacesIt(): void
	{
		$before = $this->csrfToken();

		$this->assertStringStartsWith('/panel/expired', $this->signIn(csrf: false)->getHeaderLine('Location'));
		$this->assertSame('/panel', $this->signIn()->getHeaderLine('Location'));
		$this->assertNotSame($before, $this->csrfToken());

		$this->switchLanguage('de', ['csrf' => false, 'headers' => ['X-CSRF-Token' => $before]]);
		$this->assertNull($this->panelLocale());
	}

	public function testUploadsNeedTheToken(): void
	{
		$this->signIn();

		$response = $this->makeRequest('POST', '/media/image', [
			'csrf' => false,
			'headers' => ['Accept' => 'application/json', 'X-Requested-With' => 'xmlhttprequest'],
		]);

		$this->assertResponseStatus(403, $response);
		$this->assertFalse(json_decode((string) $response->getBody(), true)['ok']);
	}

	public function testABearerTokenNeedsNoCsrfTokenAndOutranksTheSession(): void
	{
		$this->signIn();

		$refused = $this->switchLanguage('de', ['csrf' => false, 'authToken' => 'unknown']);
		$this->assertResponseStatus(303, $refused);
		$this->assertStringStartsWith('/panel/login', $refused->getHeaderLine('Location'));
		$this->assertNull($this->panelLocale(), 'the session cookie does not stand in for the token');

		$token = $this->createAuthenticatedUser();
		$this->assertResponseStatus(303, $this->switchLanguage('de', ['csrf' => false, 'authToken' => $token]));
	}

	public function testEveryPanelFormThatPostsCarriesTheToken(): void
	{
		$views = dirname(__DIR__, 2) . '/panel/views';
		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
			$views,
			\FilesystemIterator::SKIP_DOTS,
		));
		$forms = 0;

		foreach ($files as $file) {
			$source = (string) file_get_contents((string) $file);
			// The opening tag, which may hold PHP blocks with a `>` of their own.
			preg_match_all('/<form\b(?:<\?.*?\?>|[^>])*>(.{0,200})/s', $source, $matches, PREG_SET_ORDER);

			foreach ($matches as [$tag, $after]) {
				if (!preg_match('/method="post"/i', $tag)) {
					continue;
				}

				$forms++;
				$this->assertStringContainsString(
					"\$this->include('component/csrf')",
					$after,
					"A form in {$file} posts without the CSRF token",
				);
			}
		}

		$this->assertGreaterThan(20, $forms);
	}

	private function signIn(bool $csrf = true): ResponseInterface
	{
		return $this->makeRequest('POST', '/panel/login', [
			'body' => ['login' => 'csrf-user', 'password' => 'password', 'rememberme' => false],
			'csrf' => $csrf,
		]);
	}

	/** @param array<string, mixed> $options */
	private function switchLanguage(string $locale, array $options = []): ResponseInterface
	{
		$options['headers'] = ($options['headers'] ?? []) + ['Referer' => 'http://localhost/panel'];
		$options['server'] = ['REMOTE_ADDR' => '192.0.2.7'];

		if (!isset($options['form'])) {
			$options['body'] = ['locale' => $locale];
		}

		return $this->makeRequest('POST', 'http://localhost/panel/locale', $options);
	}

	private function panelLocale(): ?string
	{
		return new Users($this->db())->byLogin('csrf-user')?->panelLocale;
	}
}
