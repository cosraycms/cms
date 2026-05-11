<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Tests\TestCase;
use Celemas\Cms\Util\Time;

final class TimeTest extends TestCase
{
	public function testToIsoDateTime(): void
	{
		$timestamp = 1_704_067_200; // 2024-01-01 00:00:00 UTC
		$this->assertSame('2024-01-01 00:00:00', Time::toIsoDateTime($timestamp));
	}

	public function testToIsoDate(): void
	{
		$timestamp = 1_704_067_200; // 2024-01-01
		$this->assertSame('2024-01-01', Time::toIsoDate($timestamp));
	}

	public function testToIsoDateTimeWithDifferentTimestamp(): void
	{
		$timestamp = 1_711_843_200; // 2024-03-31 00:00:00 UTC
		$this->assertSame('2024-03-31 00:00:00', Time::toIsoDateTime($timestamp));
	}

	public function testToIsoDateWithDifferentTimestamp(): void
	{
		$timestamp = 1_711_843_200; // 2024-03-31
		$this->assertSame('2024-03-31', Time::toIsoDate($timestamp));
	}
}
