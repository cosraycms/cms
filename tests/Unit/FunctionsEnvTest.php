<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Tests\TestCase;

use function Celemas\Cms\env;

final class FunctionsEnvTest extends TestCase
{
	/** @var array<string, array{env: bool, envValue: mixed, server: bool, serverValue: mixed, process: bool, processValue: string|null}> */
	private array $environment = [];

	protected function setUp(): void
	{
		parent::setUp();

		$this->clearEnvironment('TEST');
	}

	protected function tearDown(): void
	{
		foreach ($this->environment as $key => $value) {
			if ($value['env']) {
				$_ENV[$key] = $value['envValue'];
			} else {
				unset($_ENV[$key]);
			}

			if ($value['server']) {
				$_SERVER[$key] = $value['serverValue'];
			} else {
				unset($_SERVER[$key]);
			}

			if ($value['process']) {
				putenv($key . '=' . $value['processValue']);
			} else {
				putenv($key);
			}
		}

		parent::tearDown();
	}

	public function testFunctionEnvGetsValueFromServerEnvironment(): void
	{
		$_SERVER['TEST'] = 'server';

		$this->assertSame('server', env('TEST'));
	}

	public function testFunctionEnvFallsBackToEnvironment(): void
	{
		$_ENV['TEST'] = 'env';

		$this->assertSame('env', env('TEST'));
	}

	public function testFunctionEnvPrefersServerOverEnvironment(): void
	{
		$_SERVER['TEST'] = 'server';
		$_ENV['TEST'] = 'env';

		$this->assertSame('server', env('TEST'));
	}

	public function testFunctionEnvIgnoresProcessEnvironment(): void
	{
		putenv('TEST=process');

		$this->assertNull(env('TEST'));
	}

	public function testFunctionEnvGetsDefaultValue(): void
	{
		$this->assertSame('2001', env('TEST', '2001'));
	}

	public function testFunctionEnvReturnsNullForMissingValueWithoutDefault(): void
	{
		$this->assertNull(env('TEST'));
	}

	public function testFunctionEnvDoesNotCastValues(): void
	{
		foreach (['true', 'false', 'null', 'empty', '1', '0', 'yes', 'no', 'on', 'off'] as $value) {
			$_ENV['TEST'] = $value;
			$this->assertSame($value, env('TEST'));
		}
	}

	private function clearEnvironment(string ...$keys): void
	{
		foreach ($keys as $key) {
			$processValue = getenv($key);
			$this->environment[$key] = [
				'env' => array_key_exists($key, $_ENV),
				'envValue' => $_ENV[$key] ?? null,
				'server' => array_key_exists($key, $_SERVER),
				'serverValue' => $_SERVER[$key] ?? null,
				'process' => $processValue !== false,
				'processValue' => $processValue === false ? null : $processValue,
			];

			unset($_ENV[$key], $_SERVER[$key]);
			putenv($key);
		}
	}
}
