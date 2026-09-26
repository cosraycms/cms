<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Library;
use Cosray\Tests\TestCase;
use DateTimeImmutable;

/**
 * @internal
 *
 * @coversNothing
 */
final class AssetLibraryTest extends TestCase
{
	public function testUploadRangesCutRelativeToNow(): void
	{
		$now = new DateTimeImmutable('2026-09-26T15:30:00+02:00');

		$this->assertSame('2026-09-19T15:30:00+02:00', Library::rangeSince('7d', $now));
		$this->assertSame('2026-08-27T15:30:00+02:00', Library::rangeSince('30d', $now));
		$this->assertSame('2026-01-01T00:00:00+02:00', Library::rangeSince('year', $now));
		$this->assertNull(Library::rangeSince('', $now));
		$this->assertNull(Library::rangeSince('forever', $now));
	}

	public function testFilterKindsKeepTheVocabularyAndCollapseAllToNone(): void
	{
		$this->assertSame(['image', 'audio'], Library::filterKinds('audio, image,file'));
		$this->assertSame(['video'], Library::filterKinds(['video', 'bogus']));
		$this->assertSame([], Library::filterKinds('image,video,audio,document'));
		$this->assertSame([], Library::filterKinds(''));
	}
}
