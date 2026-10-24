<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Node\TestJsonPage;

/**
 * End-to-end tests for the frontend content-negotiation JSON read.
 *
 * Headless consumers fetch the public page URL with
 * `Accept: application/json` and receive the API's resolved node.
 *
 * @internal
 *
 * @coversNothing
 */
final class ContentNegotiationTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->loadFixtures('basic-types');
	}

	public function testPageUrlReturnsTheResolvedNodeForJsonAccept(): void
	{
		$this->page('test-page', 'negotiation-node', 'Negotiated', '/negotiation-test');

		$response = $this->makeRequest('GET', '/negotiation-test', [
			'headers' => ['Accept' => 'application/json'],
		]);

		$payload = $this->assertJsonResponse($response, 200);
		$this->assertSame('negotiation-node', $payload['uid'] ?? null);
		$this->assertSame('Negotiated', $payload['title'] ?? null);
		$this->assertSame('Negotiated', $payload['fields']['title'] ?? null);
		$this->assertSame('/negotiation-test', $payload['paths']['en'] ?? null);
	}

	public function testPageJsonExposesNoUserData(): void
	{
		$user = $this->createTestUser([
			'uid' => 'negotiation-editor',
			'username' => 'negotiation-login',
			'email' => 'negotiation-editor@example.com',
			'data' => ['name' => 'Negotiation Editor', 'phone' => '+49 951 0000000'],
		]);
		$this->page('test-page', 'negotiation-private', 'Public title', '/negotiation-private', $user);

		$response = $this->makeRequest('GET', '/negotiation-private', [
			'headers' => ['Accept' => 'application/json'],
		]);

		$this->assertJsonResponse($response, 200);
		$body = (string) $response->getBody();
		$this->assertStringNotContainsString('negotiation-editor@example.com', $body);
		$this->assertStringNotContainsString('negotiation-login', $body);
		$this->assertStringNotContainsString('+49 951 0000000', $body);
	}

	public function testANodeComposesItsOwnJson(): void
	{
		$this->db()->execute("INSERT INTO cms.types (handle) VALUES ('test-json-page') ON CONFLICT DO NOTHING")->run();
		$this->page('test-json-page', 'composed-page', 'Composed', '/composed');

		$payload = $this->assertJsonResponse($this->makeRequest('GET', '/composed', [
			'headers' => ['Accept' => 'application/json'],
		]), 200);

		$this->assertTrue($payload['composed']);
		$this->assertSame('Composed', $payload['page']['title']);
		$this->assertSame(['title' => 'Composed'], $payload['page']['fields']);
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$bootstrap = parent::createBootstrap($config);
		$bootstrap->node(TestJsonPage::class);

		return $bootstrap;
	}

	private function page(string $type, string $uid, string $title, string $path, int $user = 1): void
	{
		$row = $this->db()->execute('SELECT type FROM cms.types WHERE handle = :type', ['type' => $type])->one();
		$nodeId = $this->createTestNode([
			'uid' => $uid,
			'type' => (int) $row['type'],
			'creator' => $user,
			'editor' => $user,
			'content' => ['title' => ['type' => Text::class, 'value' => ['en' => $title]]],
			'title' => ['en' => $title],
		]);
		$this->createTestPath($nodeId, $path);
	}
}
