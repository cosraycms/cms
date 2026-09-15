<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Celema\Core\App;
use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Node\RestrictedPage;
use PHPUnit\Framework\Attributes\DataProvider;

final class PasswordAccessTest extends End2EndTestCase
{
	protected function createApp(array $settings = []): App
	{
		return parent::createApp([
			'app.secret' => 'test-application-secret',
			'access.passwords' => ['staff' => password_hash('test-shared-password', PASSWORD_BCRYPT)],
			...$settings,
		]);
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$bootstrap = parent::createBootstrap($config);
		$bootstrap->node(RestrictedPage::class);

		return $bootstrap;
	}

	protected function setUp(): void
	{
		parent::setUp();
		$type = $this->createTestType('restricted-page');
		$node = $this->createTestNode(['uid' => 'password-page', 'type' => $type]);
		$this->createTestPath($node, '/restricted', 'en');
	}

	public function testUnlockAndLogoutProtectHtmlAndJsonWithoutGrantingPanelAccess(): void
	{
		$this->assertResponseStatus(303, $this->makeRequest('GET', '/restricted'));
		$this->assertResponseStatus(401, $this->makeRequest('GET', '/restricted', ['headers' => [
			'Accept' => 'application/json',
		]]));
		$this->assertResponseStatus(403, $this->makeRequest('POST', '/restricted'));

		$token = $this->token();
		$id = session_id();
		$this->assertResponseStatus(401, $this->unlock($token, 'wrong'));
		$response = $this->unlock($token);
		$this->assertResponseStatus(303, $response);
		$this->assertSame('/restricted', $response->getHeaderLine('Location'));
		$this->assertNotSame($id, session_id());
		$response = $this->makeRequest('GET', '/restricted', ['headers' => ['Accept' => 'application/json']]);
		$this->assertResponseOk($response);
		$this->assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
		$this->assertResponseStatus(303, $this->makeRequest('GET', '/cp'));

		$this->assertResponseStatus(303, $this->makeRequest('POST', '/access/staff/logout', ['body' => [
			'_token' => $this->token(),
		]]));
		$this->assertResponseStatus(303, $this->makeRequest('GET', '/restricted'));
	}

	public function testCsrfIsRequiredForUnlockAndLogout(): void
	{
		$this->token();
		$this->assertResponseStatus(403, $this->unlock('invalid'));
		$this->assertResponseStatus(303, $this->unlock($this->token()));
		$this->assertResponseStatus(403, $this->makeRequest('POST', '/access/staff/logout'));
		$this->assertResponseOk($this->makeRequest('GET', '/restricted'));
	}

	public function testRateLimitSurvivesSessionReplacement(): void
	{
		$token = $this->token();
		for ($i = 0; $i < 10; $i++) {
			$this->assertResponseStatus(401, $this->unlock($token, 'wrong'));
		}
		$_SESSION = [];
		$response = $this->unlock($this->token());
		$this->assertResponseStatus(429, $response);
		$this->assertSame('900', $response->getHeaderLine('Retry-After'));
	}

	public function testExpiredOrRotatedCredentialsRevokeGrants(): void
	{
		$this->unlock($this->token());
		$_SESSION['access.staff']['expires'] = time() - 1;
		$this->assertResponseStatus(303, $this->makeRequest('GET', '/restricted'));
		$this->unlock($this->token());
		$this->app = $this->createApp(['access.passwords' => ['staff' => password_hash(
			'rotated-password',
			PASSWORD_BCRYPT,
		)]]);
		$this->assertResponseStatus(303, $this->makeRequest('GET', '/restricted'));
	}

	public static function unsafeTargets(): array
	{
		return [
			['https://example.org'],
			['//example.org'],
			['/\\example.org'],
			['/%2fexample.org'],
			["/\r\nLocation: evil"],
		];
	}

	#[DataProvider('unsafeTargets')]
	public function testUnlockCannotRedirectOffSite(string $target): void
	{
		$response = $this->makeRequest('POST', '/access/staff', ['body' => [
			'_token' => $this->token(),
			'password' => 'test-shared-password',
			'next' => $target,
		]]);
		$this->assertResponseStatus(303, $response);
		$this->assertSame('/', $response->getHeaderLine('Location'));
	}

	private function token(): string
	{
		$response = $this->makeRequest('GET', '/access/staff');
		$this->assertResponseOk($response);
		preg_match('/name="_token" value="([^"]+)"/', (string) $response->getBody(), $matches);

		return $matches[1];
	}

	private function unlock(
		#[\SensitiveParameter]
		string $token,
		#[\SensitiveParameter]
		string $password = 'test-shared-password',
	): \Psr\Http\Message\ResponseInterface {
		return $this->makeRequest('POST', '/access/staff', ['body' => [
			'_token' => $token,
			'password' => $password,
			'next' => '/restricted',
		]]);
	}
}
