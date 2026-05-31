<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit\View\Boiler\Error;

use Cosray\Tests\TestCase;
use Cosray\View\Boiler\Error\WhoopsHandler;
use Exception;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class WhoopsHandlerTest extends TestCase
{
	public function testAvailableReturnsTrueWhenWhoopsIsInstalled(): void
	{
		$this->assertTrue(WhoopsHandler::available());
	}

	#[RunInSeparateProcess]
	public function testAvailableReturnsFalseWithoutWhoopsAutoloader(): void
	{
		$this->assertTrue(class_exists(WhoopsHandler::class));
		$loaders = spl_autoload_functions() ?: [];

		foreach ($loaders as $loader) {
			spl_autoload_unregister($loader);
		}

		try {
			$available = WhoopsHandler::available();
		} finally {
			foreach ($loaders as $loader) {
				spl_autoload_register($loader);
			}
		}

		$this->assertFalse($available);
	}

	public function testHandleReturns500Response(): void
	{
		$handler = new WhoopsHandler();
		$exception = new Exception('Test error');

		$response = $handler->handle($exception, $this->factory()->responseFactory());

		$this->assertInstanceOf(ResponseInterface::class, $response);
		$this->assertSame(500, $response->getStatusCode());
		$this->assertSame('text/html', $response->getHeaderLine('Content-type'));
	}
}
