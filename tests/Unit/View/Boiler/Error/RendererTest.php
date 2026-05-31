<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit\View\Boiler\Error;

use Celemas\Core\Exception\HttpBadRequest;
use Cosray\Tests\TestCase;
use Cosray\View\Boiler\Error\Renderer;
use Exception;

/**
 * @internal
 *
 * @coversNothing
 */
final class RendererTest extends TestCase
{
	public function testRenderBasicException(): void
	{
		$renderer = new Renderer(
			template: 'error',
			dirs: $this->templates(),
		);

		$exception = new Exception('Something went wrong', 500);
		$response = $renderer->render($exception, $this->factory()->responseFactory(), null, false);

		$this->assertSame(500, $response->getStatusCode());
		$this->assertStringContainsString('Error 500', (string) $response->getBody());
		$this->assertStringContainsString('Something went wrong', (string) $response->getBody());
	}

	public function testRenderWithInvalidStatusCodeDefaultsTo500(): void
	{
		$renderer = new Renderer(
			template: 'error',
			dirs: $this->templates(),
		);

		$exception = new Exception('Error', 0);
		$response = $renderer->render($exception, $this->factory()->responseFactory(), null, false);

		$this->assertSame(500, $response->getStatusCode());
	}

	public function testRenderWithStatusCodeAbove599DefaultsTo500(): void
	{
		$renderer = new Renderer(
			template: 'error',
			dirs: $this->templates(),
		);

		$exception = new Exception('Error', 600);
		$response = $renderer->render($exception, $this->factory()->responseFactory(), null, false);

		$this->assertSame(500, $response->getStatusCode());
	}

	public function testRenderWithValidStatusCode(): void
	{
		$renderer = new Renderer(
			template: 'error',
			dirs: $this->templates(),
		);

		$exception = new Exception('Not found', 404);
		$response = $renderer->render($exception, $this->factory()->responseFactory(), null, false);

		$this->assertSame(404, $response->getStatusCode());
	}

	public function testRenderHttpErrorWithPayload(): void
	{
		$renderer = new Renderer(
			template: 'error',
			dirs: $this->templates(),
		);

		$exception = new HttpBadRequest(message: 'Validation failed');
		$exception->payload(['errors' => ['field' => 'required']]);

		$response = $renderer->render($exception, $this->factory()->responseFactory(), null, false);

		$this->assertSame(400, $response->getStatusCode());
	}

	public function testRenderJsonOnlyRequest(): void
	{
		$renderer = new Renderer(
			template: 'error',
			dirs: $this->templates(),
		);

		$exception = new HttpBadRequest(message: 'Validation failed');
		$exception->payload(['errors' => ['field' => 'required']]);

		$request = $this
			->factory()
			->serverRequest('GET', '/test')
			->withHeader('Accept', 'application/json');

		$response = $renderer->render($exception, $this->factory()->responseFactory(), $request, false);

		$this->assertSame(400, $response->getStatusCode());
		$this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
		$this->assertJson((string) $response->getBody());
	}

	public function testRenderMixedAcceptHeaderReturnsHtml(): void
	{
		$renderer = new Renderer(
			template: 'error',
			dirs: $this->templates(),
		);

		$exception = new Exception('Error', 500);
		$request = $this
			->factory()
			->serverRequest('GET', '/test')
			->withHeader('Accept', 'text/html, application/json');

		$response = $renderer->render($exception, $this->factory()->responseFactory(), $request, false);

		$this->assertSame(500, $response->getStatusCode());
		$this->assertStringContainsString('<h1>', (string) $response->getBody());
	}

	public function testRenderWithContext(): void
	{
		$renderer = new Renderer(
			template: 'error',
			dirs: $this->templates(),
			context: ['debug' => true],
		);

		$exception = new Exception('Error', 500);
		$response = $renderer->render($exception, $this->factory()->responseFactory(), null, false);

		$this->assertSame(500, $response->getStatusCode());
	}

	private function templates(): array
	{
		return [self::root() . '/tests/Fixtures/Boiler/templates'];
	}
}
