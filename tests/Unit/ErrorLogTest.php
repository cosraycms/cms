<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\ErrorLog;
use Cosray\Tests\TestCase;
use LogicException;
use RuntimeException;

/**
 * @internal
 *
 * @coversNothing
 */
final class ErrorLogTest extends TestCase
{
	public function testWritesTheExceptionChain(): void
	{
		$exception = new RuntimeException('Outer', previous: new LogicException('Root cause'));

		$log = $this->errorLog(static fn() => new ErrorLog()->alert('Unmatched exception', [
			'exception' => $exception,
		]));

		$this->assertStringContainsString('ALERT: Unmatched exception: ', $log);
		$this->assertStringContainsString('RuntimeException: Outer', $log);
		$this->assertStringContainsString('LogicException: Root cause', $log);
	}

	public function testInterpolatesPlaceholders(): void
	{
		$log = $this->errorLog(static fn() => new ErrorLog()->warning('Login failed for {login}', [
			'login' => 'editor',
		]));

		$this->assertStringContainsString('WARNING: Login failed for editor', $log);
	}

	public function testLeavesOutDebugAndInfo(): void
	{
		$log = $this->errorLog(static function (): void {
			$logger = new ErrorLog();
			$logger->debug('Details');
			$logger->info('Progress');
			$logger->notice('PHP diagnostic');
		});

		$this->assertStringNotContainsString('Details', $log);
		$this->assertStringNotContainsString('Progress', $log);
		$this->assertStringContainsString('NOTICE: PHP diagnostic', $log);
	}
}
