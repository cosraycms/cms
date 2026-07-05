<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Util;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @covers \Cosray\Assets\Util
 */
final class SlugTest extends TestCase
{
	public function testKeepsSafeNamesVerbatim(): void
	{
		$this->assertSame('logo.png', Util::slug('logo.png'));
		$this->assertSame('report_2026-final.pdf', Util::slug('report_2026-final.pdf'));
	}

	public function testLowercasesAndTransliterates(): void
	{
		$this->assertSame('ai-hands.jpg', Util::slug('AI-Hands.JPG'));
		$this->assertSame('anderung.pdf', Util::slug('Änderung.pdf'));
		$this->assertSame('uber-uns.webp', Util::slug('Über Uns.webp'));
	}

	public function testCollapsesWhitespaceAndSeparators(): void
	{
		$this->assertSame('a-b.c.png', Util::slug("a \t b..c...png"));
		$this->assertSame('a-b.png', Util::slug('a -- b.png'));
	}

	public function testStripsUnsafeCharacters(): void
	{
		$this->assertSame('pic.png', Util::slug('pic%$?.png'));
		// NTFS alternate-data-stream artifact (U+F03A private use).
		$this->assertSame(
			'pic.pngzone.identifier',
			Util::slug("pic.png\u{F03A}Zone.Identifier"),
		);
	}

	public function testEdgeShapes(): void
	{
		$this->assertSame('', Util::slug('☺'));
		$this->assertSame('', Util::slug('...'));
		// The leading dot survives for the uid-stem fallback.
		$this->assertSame('.pdf', Util::slug('☺.pdf'));
		$this->assertSame('name', Util::slug('name.'));
	}
}
