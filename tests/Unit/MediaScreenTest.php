<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Panel\MediaScreen;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class MediaScreenTest extends TestCase
{
	public function testReadsItsStateFromTheQuery(): void
	{
		$screen = MediaScreen::fromParams('/cp/media', [
			'kind' => 'image,audio',
			'q' => ' logo ',
			'range' => '7d',
			'file' => ' abc123 ',
			'page' => '2',
		]);

		$this->assertSame('/cp/media', $screen->path);
		$this->assertSame(['image', 'audio'], $screen->kinds);
		$this->assertSame('logo', $screen->q);
		$this->assertSame('7d', $screen->range);
		$this->assertSame('abc123', $screen->file);
		$this->assertSame(2, $screen->page);
		$this->assertTrue($screen->filtered());
	}

	public function testDefaultsAnEmptyOrForeignQuery(): void
	{
		$empty = MediaScreen::fromParams('/cp/media', []);

		$this->assertSame([], $empty->kinds);
		$this->assertSame('', $empty->q);
		$this->assertSame('', $empty->range);
		$this->assertNull($empty->file);
		$this->assertSame(1, $empty->page);
		$this->assertFalse($empty->filtered());

		$foreign = MediaScreen::fromParams('/cp/media', [
			'kind' => 'nonsense,image,image',
			'range' => '8w',
			'file' => ' ',
			'page' => '0',
			'q' => ['not', 'a', 'string'],
			'foo' => '1',
		]);

		$this->assertSame(['image'], $foreign->kinds);
		$this->assertSame('', $foreign->range);
		$this->assertNull($foreign->file);
		$this->assertSame(1, $foreign->page);
		$this->assertSame('', $foreign->q);
	}

	public function testTakesTheRailsCheckboxListAsKinds(): void
	{
		$screen = MediaScreen::fromParams('/cp/media', ['kind' => ['video', 'document', 3, 'bogus']]);

		$this->assertSame(['video', 'document'], $screen->kinds);
		// Every kind means no filter, so the query plan stays flat.
		$this->assertSame(
			[],
			MediaScreen::fromParams('/cp/media', ['kind' => ['image', 'video', 'audio', 'document']])->kinds,
		);
	}

	public function testWritesItsStateIntoTheUrlAndDropsDefaults(): void
	{
		$this->assertSame('/cp/media', MediaScreen::fromParams('/cp/media', [])->url());

		$screen = new MediaScreen('/cp/media', ['image', 'audio'], 'logo beer', 'year', 'abc', 2);

		// The page returns to the first unless given: a changed filter or
		// search starts over, only the paging link passes it on.
		$this->assertSame('/cp/media?kind=image%2Caudio&q=logo%20beer&range=year&file=abc', $screen->url());
		$this->assertSame('/cp/media?kind=video&q=logo%20beer&range=year&file=abc', $screen->url(['kind' => [
			'video',
		]]));
		$this->assertSame('/cp/media?kind=image%2Caudio&q=logo%20beer&range=year', $screen->url(['file' => null]));
		$this->assertSame('/cp/media?kind=image%2Caudio&range=year&file=abc', $screen->url(['q' => '']));
		$this->assertSame('/cp/media?kind=image%2Caudio&q=logo%20beer&range=year&file=abc&page=3', $screen->url([
			'page' => 3,
		]));
		$this->assertSame('/cp/media', $screen->url(['kind' => [], 'q' => '', 'range' => '', 'file' => null]));
	}

	public function testAddressesAPathBelowTheScreenWithTheStateKept(): void
	{
		$screen = new MediaScreen('/cp/media', ['image'], '', '', 'abc');

		$this->assertSame('/cp/media/abc/delete?kind=image&file=abc', $screen->url(below: 'abc/delete'));
	}

	public function testQueryHoldsTheNonEmptyParametersForHiddenInputs(): void
	{
		$screen = new MediaScreen('/cp/media', ['image', 'audio'], 'logo', '', 'abc', 1);

		$this->assertSame(['kind' => 'image,audio', 'q' => 'logo', 'file' => 'abc'], $screen->query());
		$this->assertSame(['kind' => 'image,audio', 'file' => 'abc'], $screen->query(['q' => '']));
		$this->assertSame(
			['kind' => 'image,audio', 'q' => 'logo', 'file' => 'abc', 'page' => '2'],
			$screen->query(['page' => 2]),
		);
	}

	public function testWithFileKeepsEverythingElse(): void
	{
		$screen = new MediaScreen('/cp/media', ['image'], 'logo', '30d', 'abc', 2);
		$other = $screen->withFile('def');
		$none = $screen->withFile(null);

		$this->assertSame('def', $other->file);
		$this->assertNull($none->file);

		foreach ([$other, $none] as $copy) {
			$this->assertSame(['image'], $copy->kinds);
			$this->assertSame('logo', $copy->q);
			$this->assertSame('30d', $copy->range);
			$this->assertSame(2, $copy->page);
		}
	}

	public function testRoundTripsThroughUrlAndParams(): void
	{
		$screen = new MediaScreen('/cp/media', ['image', 'document'], 'beer', '30d', 'a1b2', 3);
		$query = (string) parse_url($screen->url(['page' => $screen->page]), PHP_URL_QUERY);
		$params = [];
		parse_str($query, $params);

		$this->assertEquals($screen, MediaScreen::fromParams('/cp/media', $params));
	}
}
