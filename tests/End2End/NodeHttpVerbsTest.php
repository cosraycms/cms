<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Node\HttpVerbsPage;

/**
 * End-to-end tests for the node HTTP verb interfaces.
 *
 * A node implementing one of them answers that method on its own public
 * path, ahead of the CMS defaults (render, JSON negotiation, 400).
 *
 * @internal
 *
 * @coversNothing
 */
final class NodeHttpVerbsTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->loadFixtures('basic-types');
		$this->createHandlerNode();
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$bootstrap = parent::createBootstrap($config);
		$bootstrap->node(HttpVerbsPage::class);

		return $bootstrap;
	}

	public function testGetHookReplacesTheDefaultRender(): void
	{
		$response = $this->makeRequest('GET', '/http-verbs');

		$this->assertResponseOk($response);
		$this->assertSame('get:', $this->getHtmlResponse($response));
	}

	public function testPostReachesItsHookWithTheSubmittedBody(): void
	{
		$response = $this->formRequest('POST', '/http-verbs', 'marker=sent');

		$this->assertResponseOk($response);
		$this->assertSame('post:sent', $this->getHtmlResponse($response));
	}

	public function testPutReachesItsHookWithTheSubmittedBody(): void
	{
		// PHP never populates the parsed body for PUT; Util\Form reads the
		// raw body so a handler sees the same array a POST would get.
		$response = $this->formRequest('PUT', '/http-verbs', 'marker=replaced');

		$this->assertResponseOk($response);
		$this->assertSame('put:replaced', $this->getHtmlResponse($response));
	}

	public function testDeleteReachesItsHook(): void
	{
		$response = $this->makeRequest('DELETE', '/http-verbs');

		$this->assertResponseOk($response);
		$this->assertSame('delete:', $this->getHtmlResponse($response));
	}

	public function testHooksWinOverJsonContentNegotiation(): void
	{
		$get = $this->makeRequest('GET', '/http-verbs', [
			'headers' => ['Accept' => 'application/json'],
		]);
		$this->assertSame('get:', $this->getHtmlResponse($get));

		// Before the hooks took precedence this was a 400: the XHR branch
		// rejected every method but GET before dispatch ran.
		$post = $this->makeRequest('POST', '/http-verbs', [
			'headers' => ['Accept' => 'application/json'],
		]);
		$this->assertResponseOk($post);
		$this->assertSame('post:', $this->getHtmlResponse($post));
	}

	public function testMethodWithoutAHookIsRejected(): void
	{
		$type = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-page'",
		)->one();
		$this->assertNotEmpty($type);
		$nodeId = $this->createTestNode([
			'uid' => 'verbs-plain-node',
			'type' => (int) $type['type'],
			'published' => true,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Plain']],
			],
		]);
		$this->createTestPath($nodeId, '/verbs-plain');

		$this->assertResponseStatus(400, $this->makeRequest('PUT', '/verbs-plain'));
		$this->assertResponseStatus(400, $this->makeRequest('DELETE', '/verbs-plain'));
	}

	public function testPreviewRunsThroughTheGetHook(): void
	{
		$this->authenticateAs('editor');
		$response = $this->makeRequest('GET', '/preview/http-verbs');

		$this->assertResponseOk($response);
		$this->assertSame('get:', $this->getHtmlResponse($response));
	}

	public function testPreviewReturnsNotFoundForAnUnknownPath(): void
	{
		$this->authenticateAs('editor');

		$this->assertResponseStatus(404, $this->makeRequest('GET', '/preview/no-such-page'));
	}

	private function createHandlerNode(): void
	{
		$typeId = $this->createTestType('http-verbs-page');
		$nodeId = $this->createTestNode([
			'uid' => 'verbs-node',
			'type' => $typeId,
			'published' => true,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => 'Http Verbs']],
			],
		]);
		$this->createTestPath($nodeId, '/http-verbs');
	}

	private function formRequest(string $method, string $uri, string $body): object
	{
		return $this->makeRequest($method, $uri, [
			'body' => $body,
			'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
		]);
	}
}
