<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Container\Container;
use Cosray\ContainerLogger;
use Cosray\Tests\TestCase;
use LogicException;
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
		$exception = new RuntimeException('Outer', previous: new LogicException('Root cause'));

		$log = $this->errorLog(static fn() => $logger->alert('Unmatched exception', ['exception' => $exception]));

		$this->assertStringContainsString('ALERT: Unmatched exception: ', $log);
		$this->assertStringContainsString('RuntimeException: Outer', $log);
		$this->assertStringContainsString('LogicException: Root cause', $log);
	}

	public function testFallbackInterpolatesPlaceholders(): void
	{
		$logger = new ContainerLogger(new Container());

		$log = $this->errorLog(static fn() => $logger->warning('Login failed for {user}', ['user' => 'editor']));

		$this->assertStringContainsString('WARNING: Login failed for editor', $log);
	}

	public function testFallbackLeavesOutDebugAndInfo(): void
	{
		$logger = new ContainerLogger(new Container());

		$log = $this->errorLog(static function () use ($logger): void {
			$logger->debug('Details');
			$logger->info('Progress');
			$logger->notice('PHP diagnostic');
		});

		$this->assertStringNotContainsString('Details', $log);
		$this->assertStringNotContainsString('Progress', $log);
		$this->assertStringContainsString('NOTICE: PHP diagnostic', $log);
	}

	/**
	 * Returns what $log wrote to PHP's error log. Redirects inside the test
	 * because PHPUnit points the error log at its own capture file only
	 * after setUp().
	 */
	private function errorLog(callable $log): string
	{
		$file = (string) tempnam(sys_get_temp_dir(), 'cosray-error-log-');
		// @mago-expect lint:no-ini-set
		$previous = ini_set('error_log', $file);

		try {
			$log();

			return (string) file_get_contents($file);
		} finally {
			// @mago-expect lint:no-ini-set
			ini_set('error_log', (string) $previous);
			unlink($file);
		}
	}
}
