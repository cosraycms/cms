<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Ingest;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @covers \Cosray\Assets\Ingest::sanitizeSvgMarkup
 */
final class IngestSvgSanitizeTest extends TestCase
{
	public function testStripsScriptElement(): void
	{
		$clean = Ingest::sanitizeSvgMarkup(
			'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1"/></svg>',
		);

		$this->assertNotNull($clean);
		$this->assertStringNotContainsStringIgnoringCase('<script', $clean);
		$this->assertStringNotContainsStringIgnoringCase('alert', $clean);
	}

	public function testStripsEventHandlerAttribute(): void
	{
		$clean = Ingest::sanitizeSvgMarkup(
			'<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="1" height="1"/></svg>',
		);

		$this->assertNotNull($clean);
		$this->assertStringNotContainsStringIgnoringCase('onload', $clean);
	}

	public function testPreservesBenignSvg(): void
	{
		$clean = Ingest::sanitizeSvgMarkup(
			'<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>',
		);

		$this->assertNotNull($clean);
		$this->assertStringContainsString('<svg', $clean);
		$this->assertStringContainsString('rect', $clean);
	}

	public function testRejectsMalformedMarkupAsNull(): void
	{
		$this->assertNull(Ingest::sanitizeSvgMarkup('<<< this is not valid svg'));
	}
}
