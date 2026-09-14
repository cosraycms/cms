<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Fulltext\Snippet;
use PHPUnit\Framework\TestCase;

final class FulltextSnippetTest extends TestCase
{
	public function testOnlyFixedHighlightTagsBecomeMarkup(): void
	{
		$snippet = new Snippet("<script>alert('x')</script> & \x01Café <img onerror=\"evil()\">\x02");
		self::assertSame("<script>alert('x')</script> & Café <img onerror=\"evil()\">", $snippet->text());
		self::assertSame(
			'&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt; &amp; <mark>Café &lt;img onerror=&quot;evil()&quot;&gt;</mark>',
			$snippet->html(),
		);
		self::assertSame(
			[
				['text' => "<script>alert('x')</script> & ", 'match' => false],
				['text' => 'Café <img onerror="evil()">', 'match' => true],
			],
			$snippet->segments,
		);
	}

	public function testEmptyAndUnhighlightedSnippetsArePlainText(): void
	{
		self::assertSame('', new Snippet('')->html());
		self::assertSame('&lt;unmatched&gt;', new Snippet('<unmatched>')->html());
	}
}
