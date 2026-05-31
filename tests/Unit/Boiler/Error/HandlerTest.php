<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit\Boiler\Error;

use Celemas\Error\Handler as ErrorHandler;
use Celemas\Error\Renderer as ErrorRenderer;
use Cosray\Boiler\Error\Handler;
use Cosray\Config;
use Cosray\Tests\TestCase;
use Exception;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\NullLogger;
use ReflectionClass;
use Throwable;

/**
 * @internal
 *
 * @coversNothing
 */
final class HandlerTest extends TestCase
{
	public function testViewsMethodReturnsInstance(): void
	{
		$handler = $this->handler();

		$result = $handler->views('tests/Fixtures/Boiler/templates');

		$this->assertSame($handler, $result);
	}

	public function testTrustedMergesByDefault(): void
	{
		$handler = $this->handler();

		$result = $handler->trusted([self::class]);

		$this->assertSame($handler, $result);
	}

	public function testTrustedCanReplace(): void
	{
		$handler = $this->handler();

		$result = $handler->trusted([self::class], replace: true);

		$this->assertSame($handler, $result);
	}

	#[RunInSeparateProcess]
	public function testCreateReturnsErrorHandler(): void
	{
		$errorHandler = $this->handler()->create();

		$this->assertInstanceOf(ErrorHandler::class, $errorHandler);
		$errorHandler->restoreHandlers();
	}

	#[RunInSeparateProcess]
	public function testCreateWithDebugMode(): void
	{
		$errorHandler = $this->handler($this->errorConfig(debug: true))->create();

		$this->assertInstanceOf(ErrorHandler::class, $errorHandler);
		$errorHandler->restoreHandlers();
	}

	#[RunInSeparateProcess]
	public function testCreateUsesConfigDebugInsteadOfEnvironment(): void
	{
		$_ENV['APP_DEBUG'] = 'false';
		$errorHandler = $this->handler($this->errorConfig(debug: true))->create();
		$reflection = new ReflectionClass($errorHandler);
		$property = $reflection->getProperty('debug');
		$property->setAccessible(true);

		$this->assertTrue($property->getValue($errorHandler));
		$errorHandler->restoreHandlers();
	}

	#[RunInSeparateProcess]
	public function testProjectErrorTemplatesOverrideBuiltInFallback(): void
	{
		$errorHandler = $this->handler()->create();
		$response = $errorHandler->getResponse(new Exception('Boom'), null);

		$this->assertStringContainsString('Server Error', (string) $response->getBody());
		$errorHandler->restoreHandlers();
	}

	#[RunInSeparateProcess]
	public function testBuiltInTemplatesAreFallback(): void
	{
		$config = $this->errorConfig([
			'path.root' => self::root(),
			'path.views' => '/missing-error-templates',
		]);
		$errorHandler = $this->handler($config)->create();
		$response = $errorHandler->getResponse(new Exception('Boom'), null);

		$this->assertStringContainsString('Internal Server Error', (string) $response->getBody());
		$errorHandler->restoreHandlers();
	}

	#[RunInSeparateProcess]
	public function testCustomRendererCanReplaceDefaultRenderer(): void
	{
		$renderer = new class implements ErrorRenderer {
			public function render(
				Throwable $exception,
				ResponseFactory $factory,
				?Request $request,
				bool $debug,
			): Response {
				$response = $factory->createResponse(500);
				$response->getBody()->write('custom error');

				return $response;
			}
		};
		$config = $this->errorConfig(['error.renderer' => $renderer]);
		$errorHandler = $this->handler($config)->create();
		$response = $errorHandler->getResponse(new Exception('Boom'), null);

		$this->assertSame('custom error', (string) $response->getBody());
		$errorHandler->restoreHandlers();
	}

	private function handler(?Config $config = null): Handler
	{
		return new Handler(
			config: $config ?? $this->errorConfig(),
			factory: $this->factory(),
			logger: new NullLogger(),
		);
	}

	/** @param array<string, mixed> $settings */
	private function errorConfig(array $settings = [], bool $debug = false): Config
	{
		return new Config(self::root(), array_merge([
			'app.name' => 'cosray',
			'app.debug' => $debug,
			'app.env' => 'test',
			'path.root' => self::root(),
			'path.views' => '/tests/Fixtures/Boiler/templates',
		], $settings));
	}
}
