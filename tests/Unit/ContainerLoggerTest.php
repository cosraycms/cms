<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Container\Container;
use Cosray\ContainerLogger;
use Cosray\Tests\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface as Logger;
use RuntimeException;
use Stringable;

/**
 * @internal
 *
 * @coversNothing
 */
final class ContainerLoggerTest extends TestCase
{
	public function testForwardsToTheLoggerRegisteredLater(): void
	{
		$container = new Container();
		$logger = new ContainerLogger($container);
		$registered = new class extends AbstractLogger {
			/** @var list<array{mixed, string, array}> */
			public array $records = [];

			public function log(mixed $level, string|Stringable $message, array $context = []): void
			{
				$this->records[] = [$level, (string) $message, $context];
			}
		};
		$container->add(Logger::class, $registered);

		$log = $this->errorLog(static fn() => $logger->debug('Details', ['id' => 7]));

		$this->assertSame([['debug', 'Details', ['id' => 7]]], $registered->records);
		$this->assertSame('', $log);
	}

	public function testWritesToPhpsErrorLogWithoutARegisteredLogger(): void
	{
		$logger = new ContainerLogger(new Container());
		$exception = new RuntimeException('Outer');

		$log = $this->errorLog(static fn() => $logger->alert('Unmatched exception', ['exception' => $exception]));

		$this->assertStringContainsString('ALERT: Unmatched exception: ', $log);
		$this->assertStringContainsString('RuntimeException: Outer', $log);
	}
}
