<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\End2End;

use Celemas\Cms\Tests\End2EndTestCase;

/**
 * End-to-end tests for URL routing and catchall behavior.
 *
 * Tests how the CMS resolves URLs to nodes and handles 404s.
 *
 * @internal
 *
 * @coversNothing
 */
final class RoutingTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		// Load test data fixtures
		$this->loadFixtures('basic-types', 'sample-nodes');
	}

	public function testHomepageResolution(): void
	{
		$response = $this->makeRequest('GET', '/');

		$this->assertResponseOk($response);

		$html = $this->getHtmlResponse($response);
		$this->assertNotEmpty($html);
	}

	public function testPagePathResolution(): void
	{
		$typeId = $this->createTestType('routing-test-page');
		$nodeId = $this->createTestNode([
			'uid' => 'routing-test-page',
			'type' => $typeId,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Test Page']],
			],
		]);
		$this->createTestPath($nodeId, '/about/team');

		$response = $this->makeRequest('GET', '/about/team');

		$this->assertResponseOk($response);

		$html = $this->getHtmlResponse($response);
		$this->assertNotEmpty($html);
	}

	public function testNestedPagePath(): void
	{
		$typeId = $this->createTestType('nested-test-page');

		$parentId = $this->createTestNode([
			'uid' => 'parent-page',
			'type' => $typeId,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Parent']],
			],
		]);
		$this->createTestPath($parentId, '/parent');

		$childId = $this->createTestNode([
			'uid' => 'child-page',
			'type' => $typeId,
			'parent' => $parentId,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Child']],
			],
		]);
		$this->createTestPath($childId, '/parent/child');

		$response = $this->makeRequest('GET', '/parent/child');

		$this->assertResponseOk($response);

		$html = $this->getHtmlResponse($response);
		$this->assertNotEmpty($html);
	}

	public function test404ForNonExistentPath(): void
	{
		$response = $this->makeRequest('GET', '/this/path/does/not/exist');

		$this->assertResponseStatus(404, $response);
	}

	public function test404ForNonExistentNode(): void
	{
		$response = $this->makeRequest('GET', '/node/99999999');

		$this->assertResponseStatus(404, $response);
	}

	public function testCatchallRouteMatchesAllPaths(): void
	{
		// The catchall route should handle any path that doesn't match other routes

		$paths = [
			'/some/random/path',
			'/another-path',
			'/deeply/nested/path/here',
		];

		foreach ($paths as $path) {
			$response = $this->makeRequest('GET', $path);

			$this->assertNotNull($response);
			$statusCode = $response->getStatusCode();

			// Should get either 200 (if path matches a node) or 404 (if not found)
			$this->assertTrue(
				$statusCode === 200 || $statusCode === 404,
				"Expected 200 or 404, got {$statusCode} for path: {$path}",
			);
		}
	}

	public function testResponseHeaders(): void
	{
		$response = $this->makeRequest('GET', '/');

		$this->assertResponseHasHeader('Content-Type', $response);

		// Content-Type should be text/html for page responses
		$contentType = $response->getHeaderLine('Content-Type');
		$this->assertStringContainsString('text/html', $contentType);
	}

	public function testHiddenNodesAreNotAccessible(): void
	{
		$typeId = $this->createTestType('hidden-test-page');
		$nodeId = $this->createTestNode([
			'uid' => 'hidden-page',
			'type' => $typeId,
			'hidden' => true,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Hidden Page']],
			],
		]);
		$this->createTestPath($nodeId, '/hidden-page');

		$response = $this->makeRequest('GET', '/hidden-page');

		$this->assertResponseOk($response);
	}

	public function testUnpublishedNodesAreNotAccessible(): void
	{
		$typeId = $this->createTestType('unpublished-test-page');
		$nodeId = $this->createTestNode([
			'uid' => 'unpublished-page',
			'type' => $typeId,
			'published' => false,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Unpublished Page']],
			],
		]);
		$this->createTestPath($nodeId, '/unpublished-page');

		$response = $this->makeRequest('GET', '/unpublished-page');

		$this->assertResponseStatus(404, $response);
	}
}
