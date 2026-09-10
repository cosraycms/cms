<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\DateTime\Codec;
use Cosray\Tests\TestCase;
use DateTimeImmutable;
use DateTimeZone;

final class DateTimeCodecTest extends TestCase
{
	public function testNormalizesRfc3339OffsetsToUtc(): void
	{
		$this->assertSame('2026-07-30T17:00:00Z', Codec::normalize('2026-07-30T19:00:00+02:00'));
		$this->assertSame(
			'2026-07-30T17:00:00Z',
			Codec::format(new DateTimeImmutable('2026-07-30 19:00:00', new DateTimeZone('Europe/Berlin'))),
		);
	}

	public function testRejectsNonRfcAndImpossibleValues(): void
	{
		$this->assertNull(Codec::normalize('2026-07-30 17:00:00'));
		$this->assertNull(Codec::normalize('2026-07-30T17:00'));
		$this->assertNull(Codec::normalize('2026-02-30T17:00:00Z'));
	}

	public function testConvertsBetweenUtcStorageAndLocalInput(): void
	{
		$timezone = new DateTimeZone('Europe/Berlin');

		$this->assertSame('2026-07-30T19:00:00', Codec::input('2026-07-30T17:00:00Z', $timezone));
		$this->assertSame('2026-07-30T17:00:00Z', Codec::fromInput('2026-07-30T19:00', $timezone));
		$this->assertSame('2026-07-30T17:00:45Z', Codec::fromInput('2026-07-30T19:00:45', $timezone));
		$this->assertNull(Codec::fromInput('2026-03-29T02:30', $timezone));
	}

	public function testConvertsLegacyValuesUsingTheirTimezone(): void
	{
		$this->assertSame(
			'2026-07-30T17:00:00Z',
			Codec::fromLegacy('2026-07-30 19:00:00', new DateTimeZone('Europe/Berlin')),
		);
		$this->assertSame(
			'2026-07-30T17:00:00Z',
			Codec::fromLegacy('2026-07-30T19:00', new DateTimeZone('Europe/Berlin')),
		);
	}
}
