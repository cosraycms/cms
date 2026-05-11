<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Tests\TestCase;

use function Celemas\Cms\escape;
use function Celemas\Cms\nanoid;

final class FunctionsTest extends TestCase
{
	public function testNanoidReturns13Characters(): void
	{
		$id = nanoid();
		$this->assertSame(13, strlen($id));
	}

	public function testNanoidContainsOnlyValidCharacters(): void
	{
		$id = nanoid();
		$validChars = '123456789bcdfghklmnpqrstvwxyz';

		for ($i = 0; $i < strlen($id); $i++) {
			$this->assertStringContainsString($id[$i], $validChars);
		}
	}

	public function testNanoidGeneratesDifferentIds(): void
	{
		$id1 = nanoid();
		$id2 = nanoid();

		$this->assertNotSame($id1, $id2);
	}

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
