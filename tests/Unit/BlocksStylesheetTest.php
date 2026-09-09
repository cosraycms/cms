<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class BlocksStylesheetTest extends TestCase
{
	/** The docs show the sheet's layer block verbatim, so a site may copy either. */
	public function testTheDocsCarryTheReferenceSheetVerbatim(): void
	{
		$root = dirname(__DIR__, 2);
		$sheet = (string) file_get_contents($root . '/resources/blocks.css');
		$start = strpos($sheet, '@layer cms.blocks {');
		$docs = (string) file_get_contents($root . '/docs/blocks.md');

		$this->assertNotFalse($start);
		$this->assertSame(
			1,
			preg_match('/```css\n(@layer cms\.blocks \{\n.*?\n\})\n```/s', $docs, $match),
			'docs/blocks.md must show the sheet in a css fence',
		);
		$this->assertSame(rtrim(substr($sheet, $start)), $match[1]);
	}
}
