<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;
use Cosray\User\Tokens;
use Cosray\Users;

/**
 * @internal
 *
 * @coversNothing
 */
final class PanelTokensTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->authenticateAs('editor');
	}

	public function testAUserCreatesSeesOnceAndRevokesTheirTokens(): void
	{
		$created = $this->makeRequest('POST', '/panel/profile/tokens', ['body' => [
			'name' => 'Shop import',
			'expires' => '2099-12-31',
		]]);
		$html = $this->getHtmlResponse($created);

		$this->assertResponseOk($created);
		$this->assertSame(1, preg_match('/readonly value="([a-f0-9]{64})"/', $html, $match), $html);
		$users = new Users($this->db());
		$user = $users->byAuthToken($match[1]);
		$this->assertNotNull($user);

		$list = $this->getHtmlResponse($this->makeRequest('GET', '/panel/profile/tokens'));
		$this->assertStringContainsString('Shop import', $list);
		$this->assertStringContainsString('2099-12-31', $list);
		$this->assertStringNotContainsString($match[1], $list);

		$own = new Tokens($this->db())->list($user);
		$shop = array_values(array_filter($own, static fn(array $entry): bool => $entry['name'] === 'Shop import'))[0];
		$revoked = $this->makeRequest('POST', '/panel/profile/tokens/' . $shop['uid'] . '/delete');

		$this->assertResponseStatus(303, $revoked);
		$this->assertNull($users->byAuthToken($match[1]));
	}

	public function testAnInvalidTokenRequestExplainsItself(): void
	{
		$noName = $this->getHtmlResponse($this->makeRequest('POST', '/panel/profile/tokens', ['body' => [
			'name' => ' ',
		]]));
		$past = $this->getHtmlResponse($this->makeRequest('POST', '/panel/profile/tokens', [
			'body' => ['name' => 'Old', 'expires' => '2001-01-01'],
		]));
		$garbage = $this->getHtmlResponse($this->makeRequest('POST', '/panel/profile/tokens', [
			'body' => ['name' => 'Odd', 'expires' => 'soon-ish'],
		]));

		$this->assertStringContainsString('needs a name', $noName);
		$this->assertStringContainsString('in the future', $past);
		$this->assertStringContainsString('not a date', $garbage);
	}

	public function testATokenFromBeforeNamesExistedIsListedAsUnnamed(): void
	{
		$this
			->db()
			->execute("UPDATE cms.auth_tokens SET name = '' WHERE token = :token", [
				'token' => hash('sha256', (string) $this->defaultAuthToken),
			])
			->run();

		$list = $this->getHtmlResponse($this->makeRequest('GET', '/panel/profile/tokens'));

		$this->assertStringContainsString('Unnamed token', $list);
	}

	public function testTheProfileLinksToTheTokens(): void
	{
		$this->assertStringContainsString(
			'/panel/profile/tokens',
			$this->getHtmlResponse($this->makeRequest('GET', '/panel/profile')),
		);
	}
}
