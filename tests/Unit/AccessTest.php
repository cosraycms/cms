<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Access;
use Cosray\Exception\RuntimeException;
use Cosray\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AccessTest extends TestCase
{
	public static function invalidSettings(): array
	{
		return [
			[['access.passwords' => 'wrong']],
			[['access.passwords' => ['staff' => 'plaintext']]],
			[['access.passwords' => ['panel' => password_hash('test', PASSWORD_BCRYPT)]]],
			[['access.passwords' => ['staff' => password_hash('test', PASSWORD_BCRYPT)], 'app.secret' => null]],
		];
	}

	#[DataProvider('invalidSettings')]
	public function testInvalidCredentialsFailClosedAtBoot(array $settings): void
	{
		$this->expectException(RuntimeException::class);
		Access::validate($this->config(['app.secret' => 'test-secret', ...$settings]));
	}
}
