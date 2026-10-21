<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit\View\Boiler\Error;

use Celema\Core\Error\Handler as ErrorHandler;
use Celema\Core\Error\Renderer as ErrorRenderer;
use Celema\Core\Exception\HttpNotFound;
use Celema\Quma\Exception\ConnectionFailed;
use Cosray\Config;
use Cosray\Exception\DatabaseUnavailable;
use Cosray\Tests\Fixtures\RecordingLogger;
use Cosray\Tests\TestCase;
use Cosray\View\Boiler\Error\Handler;
use Exception;
use PDOException;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface as Logger;
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
	public function testViewsMethodSelectsCustomTemplates(): void
	{
		$config = $this->errorConfig(['path.views' => '/missing-error-templates']);
		$response = $this
			->handler($config)
			->views('tests/Fixtures/Boiler/templates')
			->create()
			->response(new Exception('Boom'), $this->psrRequest());

		$this->assertStringContainsString('<h1>Server Error</h1>', (string) $response->getBody());
	}

	public function testTrustedMergesByDefault(): void
	{
		$handler = $this->handler()->trusted([Config::class]);

		$result = $handler->trusted([self::class]);

		$this->assertSame($handler, $result);
		$this->assertSame([Config::class, self::class], $this->trustedClasses($handler));
	}

	public function testTrustedCanReplace(): void
	{
		$handler = $this->handler()->trusted([Config::class]);

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

	public function testCustomRendererCanReplaceDefaultRenderers(): void
	{
		$config = $this->errorConfig(['error.renderer' => new HandlerRenderer()]);
		$errorHandler = $this->handler($config)->create();

		$this->assertSame(
			'custom error',
			(string) $errorHandler->response(new Exception('Boom'), $this->psrRequest())->getBody(),
		);
		$this->assertSame(
			'custom error',
			(string) $errorHandler->response(new HttpNotFound(), $this->psrRequest())->getBody(),
		);
	}

	public function testCustomRendererCanBeConfiguredByClassName(): void
	{
		$config = $this->errorConfig(['error.renderer' => HandlerRenderer::class]);
		$response = $this
			->handler($config)
			->create()
			->response(new Exception('Boom'), $this->psrRequest());

		$this->assertSame('custom error', (string) $response->getBody());
	}

	public function testCustomRendererTreatsHttpErrorsAsMatched(): void
	{
		$logger = $this->createMock(Logger::class);
		$logger->expects($this->never())->method('alert');
		$config = $this->errorConfig(['error.renderer' => new HandlerRenderer()]);
		$response = $this
			->handler($config, $logger)
			->create()
			->response(new HttpNotFound(), $this->psrRequest());

		$this->assertSame('custom error', (string) $response->getBody());
	}

	public function testUnreachableDatabaseAnswersServiceUnavailable(): void
	{
		$logger = new RecordingLogger();
		$request = $this->psrRequest();
		$response = $this
			->handler(logger: $logger)
			->create()
			->response($this->connectionFailed(), $request);

		$this->assertSame(503, $response->getStatusCode());
		$this->assertSame('30', $response->getHeaderLine('Retry-After'));
		$this->assertStringContainsString('Service Unavailable', (string) $response->getBody());
		$this->assertStringNotContainsString('timeout expired', (string) $response->getBody());
		$this->assertSame(
			[['alert', "Server error 503 for {$request->getMethod()} {$request->getUri()->getPath()}"]],
			$logger->records,
		);
	}

	public function testUnreachableDatabaseReachesCustomRendererAsServiceUnavailable(): void
	{
		$renderer = new HandlerRenderer();
		$config = $this->errorConfig(['error.renderer' => $renderer]);
		$response = $this->handler($config)->create()->response($this->connectionFailed(), $this->psrRequest());

		$this->assertInstanceOf(DatabaseUnavailable::class, $renderer->rendered);
		$this->assertInstanceOf(ConnectionFailed::class, $renderer->rendered->getPrevious());
		$this->assertSame('30', $response->getHeaderLine('Retry-After'));
	}

	private function connectionFailed(): ConnectionFailed
	{
		return ConnectionFailed::from(new PDOException(
			'SQLSTATE[08006] [7] connection to server at "db", port 5432 failed: timeout expired',
		));
	}

	private function handler(?Config $config = null, ?Logger $logger = null): Handler
	{
		return new Handler(
			config: $config ?? $this->errorConfig(),
			factory: $this->factory(),
			logger: $logger ?? new NullLogger(),
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

final class HandlerRenderer implements ErrorRenderer
{
	public ?Throwable $rendered = null;

	public function render(
		Throwable $exception,
		ResponseFactory $factory,
		Request $request,
		bool $debug,
	): Response {
		$this->rendered = $exception;
		$response = $factory->createResponse(500);
		$response->getBody()->write('custom error');

		return $response;
	}
}
