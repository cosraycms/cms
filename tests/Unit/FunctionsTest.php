<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\TestCase;

use function Cosray\escape;

final class FunctionsTest extends TestCase
{
	public function testEscapeConvertsSpecialCharacters(): void
	{
		$this->assertSame('&lt;script&gt;', escape('<script>'));
		$this->assertSame('&amp;', escape('&'));
		$this->assertSame('&quot;', escape('"'));
	}

	public function testEscapePreservesQuotes(): void
	{
		// ENT_HTML5 uses &apos; for single quotes
		$this->assertSame('&apos;', escape("'"));
		$this->assertSame('&quot;', escape('"'));
	}

	public function testEscapeWithEmptyString(): void
	{
		$this->assertSame('', escape(''));
	}

	public function testEscapeWithRegularText(): void
	{
		$this->assertSame('Hello World', escape('Hello World'));
	}
}
