<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;

/**
 * End-to-end tests for the frontend content-negotiation JSON read.
 *
 * Headless consumers fetch the public page URL with
 * `Accept: application/json` and receive the serialized node payload.
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

	public function testPageUrlReturnsSerializedNodeForJsonAccept(): void
	{
		$type = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-page'",
		)->one();
		$this->assertNotEmpty($type);

		$nodeId = $this->createTestNode([
			'uid' => 'negotiation-node',
			'type' => (int) $type['type'],
			'published' => true,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Negotiated']],
			],
		]);
		$this->createTestPath($nodeId, '/negotiation-test');

		$response = $this->makeRequest('GET', '/negotiation-test', [
			'headers' => ['Accept' => 'application/json'],
		]);

		$payload = $this->assertJsonResponse($response, 200);
		$this->assertSame('negotiation-node', $payload['uid'] ?? null);
		$this->assertSame('Negotiated', $payload['title'] ?? null);
		$this->assertSame('/negotiation-test', $payload['paths']['en'] ?? null);
	}

	public function testPageJsonExposesNoUserData(): void
	{
		$type = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-page'",
		)->one();
		$user = $this->createTestUser([
			'uid' => 'negotiation-editor',
			'username' => 'negotiation-login',
			'email' => 'negotiation-editor@example.com',
			'data' => ['name' => 'Negotiation Editor', 'phone' => '+49 951 0000000'],
		]);
		$nodeId = $this->createTestNode([
			'uid' => 'negotiation-private',
			'type' => (int) $type['type'],
			'creator' => $user,
			'editor' => $user,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Public title']],
			],
		]);
		$this->createTestPath($nodeId, '/negotiation-private');

		$response = $this->makeRequest('GET', '/negotiation-private', [
			'headers' => ['Accept' => 'application/json'],
		]);

		$payload = $this->assertJsonResponse($response, 200);
		$this->assertSame('Public title', $payload['content']['title']['value']['en'] ?? null);
		$body = (string) $response->getBody();
		$this->assertStringNotContainsString('negotiation-editor@example.com', $body);
		$this->assertStringNotContainsString('negotiation-login', $body);
		$this->assertStringNotContainsString('+49 951 0000000', $body);
	}
}
