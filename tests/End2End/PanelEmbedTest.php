<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PanelEmbedTest extends End2EndTestCase
{
	private const string ADMIN = 'https://admin.example';

	protected function setUp(): void
	{
		parent::setUp();

		$this->app = $this->createApp(['api.path' => '/api', 'panel.embed' => [self::ADMIN]]);
		$this->loadFixtures('basic-types');
	}

	public function testAHandoffOpensAPanelSessionOnce(): void
	{
		$this->authenticateAs('editor');
		$handoff = $this->handoff(['next' => '/node/doc?from=embed&fields=intro']);

		$this->assertSame(201, $handoff['status']);
		$this->assertStringStartsWith('/panel/handoff/', $handoff['body']['url']);

		$first = $this->makeRequest('GET', $handoff['body']['url'], ['authToken' => '']);
		$this->assertSame(303, $first->getStatusCode());
		$this->assertSame('/panel/node/doc?from=embed&fields=intro', $first->getHeaderLine('Location'));
		$this->assertResponseOk($this->makeRequest('GET', '/panel', ['authToken' => '']));

		$second = $this->makeRequest('GET', $handoff['body']['url'], ['authToken' => '']);
		$this->assertSame('/panel/login', $second->getHeaderLine('Location'));
	}

	public function testAnExpiredHandoffOpensNothing(): void
	{
		$this->authenticateAs('editor');
		$url = $this->handoff([])['body']['url'];
		$this->db()->execute("UPDATE cms.one_time_tokens SET created = now() - interval '6 minutes'")->run();

		$response = $this->makeRequest('GET', $url, ['authToken' => '']);

		$this->assertSame('/panel/login', $response->getHeaderLine('Location'));
	}

	public function testAHandoffForAUserWithoutPanelAccessOpensNothing(): void
	{
		$usr = $this->createTestUser(['uid' => 'no-panel', 'role' => 'unknown-role']);
		$token = new \Cosray\Users($this->db())->createOneTimeToken($usr);

		$response = $this->makeRequest('GET', '/panel/handoff/' . $token, ['authToken' => '']);

		$this->assertSame('/panel/login', $response->getHeaderLine('Location'));
		$this->assertStringStartsWith('/panel/login', $this->makeRequest('GET', '/panel', [
			'authToken' => '',
		])->getHeaderLine('Location'));
	}

	public function testAHandoffNeedsTheTokenOfAPanelUser(): void
	{
		$this->assertSame(401, $this->handoff([])['status']);

		$this->defaultAuthToken = $this->createAuthenticatedUser('system');
		$this->assertSame(403, $this->handoff([])['status']);

		$this->authenticateAs('editor');
		$this->assertSame(400, $this->handoff(['next' => 'https://evil.example'])['status']);
	}

	public function testTheEmbeddedEditorShowsOnlyTheAskedFieldsWithoutPanelChrome(): void
	{
		$this->authenticateAs('editor');
		$this->document('doc');

		$response = $this->makeRequest('GET', '/panel/node/doc', ['query' => ['from' => 'embed', 'fields' => 'intro']]);
		$html = $this->getHtmlResponse($response);

		$this->assertResponseOk($response);
		$this->assertSame("frame-ancestors 'self' " . self::ADMIN, $response->getHeaderLine('Content-Security-Policy'));
		$this->assertStringContainsString('data-embed-uid="doc"', $html);
		$this->assertStringContainsString('data-embed-cancel', $html);
		$this->assertStringContainsString('name="publish" value="1"', $html);
		$this->assertStringContainsString('content[intro]', $html);
		$this->assertStringNotContainsString('content[title]', $html);
		$this->assertStringNotContainsString('cms-shell', $html);
	}

	public function testWithoutEmbedOriginsThePanelRendersAsUsualAndRefusesFraming(): void
	{
		$this->app = $this->createApp(['api.path' => '/api']);
		$this->authenticateAs('editor');
		$this->document('plain');

		$response = $this->makeRequest('GET', '/panel/node/plain', ['query' => [
			'from' => 'embed',
			'fields' => 'intro',
		]]);
		$html = $this->getHtmlResponse($response);

		$this->assertSame("frame-ancestors 'self'", $response->getHeaderLine('Content-Security-Policy'));
		$this->assertStringNotContainsString('data-embed', $html);
		$this->assertStringContainsString('content[title]', $html);
	}

	public function testEmbeddedCreationPrefillsContentAndFlagsTheNewNode(): void
	{
		$this->authenticateAs('editor');
		$content = json_encode(['title' => ['value' => ['zxx' => 'Prefilled title']]]);

		$form = $this->makeRequest('GET', '/panel/node/create/test-document', [
			'query' => ['from' => 'embed', 'content' => $content],
		]);
		$this->assertStringContainsString('Prefilled title', $this->getHtmlResponse($form));

		$created = $this->makeRequest('POST', '/panel/node/create/test-document', [
			'query' => ['from' => 'embed', 'fields' => 'title'],
			'body' => [
				'uid' => 'embedded-new',
				'content' => ['title' => ['value' => ['zxx' => 'Created inside']]],
				'_complete' => '1',
			],
		]);

		$this->assertSame(303, $created->getStatusCode());
		$this->assertSame(
			'/panel/node/embedded-new?from=embed&fields=title&created=1',
			$created->getHeaderLine('Location'),
		);
	}

	/**
	 * @param array<string, string> $body
	 * @return array{status: int, body: array<string, mixed>}
	 */
	private function handoff(array $body): array
	{
		$response = $this->makeRequest('POST', '/api/handoff', [
			'body' => $body === [] ? '' : json_encode($body),
			'headers' => ['Content-Type' => 'application/json'],
		]);

		return [
			'status' => $response->getStatusCode(),
			'body' => json_decode((string) $response->getBody(), true) ?? [],
		];
	}

	private function document(string $uid): void
	{
		$type = (int) $this->db()->execute("SELECT type FROM cms.types WHERE handle = 'test-document'")->one()['type'];
		$this->createTestNode([
			'uid' => $uid,
			'type' => $type,
			'content' => ['title' => ['type' => Text::class, 'value' => ['zxx' => 'A document']]],
		]);
	}
}
