<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Richtext\Inline;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RichtextInlineTest extends TestCase
{
	/** @param list<array<string, mixed>> $runs */
	private static function doc(array $runs): array
	{
		return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => $runs]]];
	}

	public function testRendersTheMarksEscaped(): void
	{
		$doc = self::doc([
			['type' => 'text', 'text' => 'a & '],
			['type' => 'text', 'text' => '<b>', 'marks' => [['type' => 'bold']]],
			['type' => 'text', 'text' => ' c', 'marks' => [['type' => 'italic'], ['type' => 'strike']]],
		]);

		$this->assertSame('a &amp; <strong>&lt;b&gt;</strong><em><s> c</s></em>', Inline::html($doc));
		$this->assertSame('a & <b> c', Inline::text($doc));
	}

	public function testAPlainStringStaysPlainText(): void
	{
		$this->assertSame('&lt;em&gt;old&lt;/em&gt;', Inline::html('<em>old</em>'));
		$this->assertSame('<em>old</em>', Inline::text(' <em>old</em> '));
		$this->assertSame('', Inline::html(null));
	}

	public function testAcceptsOnlyItsVocabulary(): void
	{
		$this->assertTrue(Inline::valid(null));
		$this->assertTrue(Inline::valid('plain'));
		$this->assertTrue(Inline::valid(self::doc([[
			'type' => 'text',
			'text' => 'x',
			'marks' => [['type' => 'strike']],
		]])));

		$this->assertFalse(Inline::valid(42));
		$this->assertFalse(Inline::valid(['type' => 'paragraph']));
		$this->assertFalse(Inline::valid(self::doc([[
			'type' => 'text',
			'text' => 'x',
			'marks' => [['type' => 'code']],
		]])));
		$this->assertFalse(Inline::valid(self::doc([['type' => 'hardBreak']])));
		$this->assertFalse(Inline::valid([
			'type' => 'doc',
			'content' => [
				['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'one']]],
				['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'two']]],
			],
		]));
		$this->assertFalse(Inline::valid([
			'type' => 'doc',
			'content' => [
				['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'x']]],
			],
		]));
	}

	public function testReadsWhatAWriterHandsIn(): void
	{
		$doc = self::doc([['type' => 'text', 'text' => 'x']]);

		$this->assertSame($doc, Inline::read($doc));
		$this->assertSame($doc, Inline::read((string) json_encode($doc)));
		$this->assertSame(self::doc([['type' => 'text', 'text' => 'one line']]), Inline::read(" one\nline "));
		$this->assertSame(
			self::doc([['type' => 'text', 'text' => '{"not": "a doc"}']]),
			Inline::read('{"not": "a doc"}'),
		);
		$this->assertNull(Inline::read('   '));
		$this->assertNull(Inline::read(self::doc([])));
		$this->assertNull(Inline::read(['type' => 'doc', 'content' => [['type' => 'bulletList']]]));
	}
}
