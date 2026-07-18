<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit\View\Boiler\Error;

use Celema\Core\Error\Handler as ErrorHandler;
use Celema\Core\Error\Renderer as ErrorRenderer;
use Cosray\Config;
use Cosray\Tests\TestCase;
use Cosray\View\Boiler\Error\Handler;
use Exception;
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
		$trusted = $this->trustedClasses($handler);

		$this->assertSame($handler, $result);
		$this->assertContains(Config::class, $trusted);
		$this->assertContains(self::class, $trusted);
	}

	public function testTrustedCanReplace(): void
	{
		$handler = $this->handler();

		$result = $handler->trusted([self::class], replace: true);

		$this->assertSame($handler, $result);
		$this->assertSame([self::class], $this->trustedClasses($handler));
	}

	public function testCreateReturnsErrorHandler(): void
	{
		$errorHandler = $this->handler()->create();

		$this->assertInstanceOf(ErrorHandler::class, $errorHandler);
	}

	public function testCreateUsesConfigDebugInsteadOfEnvironment(): void
	{
		$hadDebug = array_key_exists('APP_DEBUG', $_ENV);
		$previousDebug = $_ENV['APP_DEBUG'] ?? null;
		$_ENV['APP_DEBUG'] = 'false';

		try {
			$errorHandler = $this->handler($this->errorConfig([
				'error.whoops' => false,
			], debug: true))->create();

			$this->throws(Exception::class, 'Boom');

			$errorHandler->response(new Exception('Boom'), $this->psrRequest());
		} finally {
			if ($hadDebug) {
				$_ENV['APP_DEBUG'] = $previousDebug;
			} else {
				unset($_ENV['APP_DEBUG']);
			}
		}
	}

	public function testProjectErrorTemplatesOverrideBuiltInFallback(): void
	{
		$errorHandler = $this->handler()->create();
		$response = $errorHandler->response(new Exception('Boom'), $this->psrRequest());

		$this->assertStringContainsString('Server Error', (string) $response->getBody());
	}

	public function testBuiltInTemplatesAreFallback(): void
	{
		$config = $this->errorConfig([
			'path.root' => self::root(),
			'path.views' => '/missing-error-templates',
		]);
		$errorHandler = $this->handler($config)->create();
		$response = $errorHandler->response(new Exception('Boom'), $this->psrRequest());

		$this->assertStringContainsString('Internal Server Error', (string) $response->getBody());
	}

	public function testCustomRendererCanReplaceDefaultRenderer(): void
	{
		$renderer = new class implements ErrorRenderer {
			public function render(
				Throwable $exception,
				ResponseFactory $factory,
				Request $request,
				bool $debug,
			): Response {
				$response = $factory->createResponse(500);
				$response->getBody()->write('custom error');

				return $response;
			}
		};
		$config = $this->errorConfig(['error.renderer' => $renderer]);
		$errorHandler = $this->handler($config)->create();
		$response = $errorHandler->response(new Exception('Boom'), $this->psrRequest());

		$this->assertSame('custom error', (string) $response->getBody());
	}

	private function handler(?Config $config = null): Handler
	{
		return new Handler(
			config: $config ?? $this->errorConfig(),
			factory: $this->factory(),
			logger: new NullLogger(),
		);
	}

	/** @return list<class-string> */
	private function trustedClasses(Handler $handler): array
	{
		$reflection = new ReflectionClass($handler);
		$property = $reflection->getProperty('trusted');

		return $property->getValue($handler);
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
