<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Meta;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class AssetMetaTest extends TestCase
{
	public function testKeepsLocalizedTextAndScalars(): void
	{
		$meta = Meta::apply(
			[],
			[
				'alt' => ['de' => ' Sudhaus ', 'en' => 'Brewhouse'],
				'title' => ['de' => 'Titel'],
				'caption' => ['de' => 'Bildunterschrift'],
				'attribution' => ' Foto: M. Huber ',
				'focal' => ['x' => 0.25, 'y' => 0.75],
			],
			['de', 'en'],
			true,
		);

		$this->assertSame(
			[
				'alt' => ['de' => 'Sudhaus', 'en' => 'Brewhouse'],
				'title' => ['de' => 'Titel'],
				'caption' => [
					'de' => [
						'type' => 'doc',
						'content' => [[
							'type' => 'paragraph',
							'content' => [['type' => 'text', 'text' => 'Bildunterschrift']],
						]],
					],
				],
				'attribution' => 'Foto: M. Huber',
				'focal' => ['x' => 0.25, 'y' => 0.75],
			],
			$meta,
		);
	}

	public function testDropsEmptyValuesAndUnknownLocales(): void
	{
		$meta = Meta::apply(
			[],
			[
				'alt' => ['de' => '  ', 'en' => 'Only English', 'xx' => 'Rejected'],
				'title' => ['de' => ''],
				'attribution' => '   ',
			],
			['de', 'en'],
			true,
		);

		$this->assertSame(['alt' => ['en' => 'Only English']], $meta);
	}

	public function testClearsManagedKeysButKeepsOthers(): void
	{
		$stored = [
			'alt' => ['de' => 'Alt'],
			'attribution' => 'Old attribution',
			'legacy' => 'keep me',
		];

		$meta = Meta::apply($stored, ['alt' => ['de' => 'New alt']], ['de'], false);

		$this->assertSame(['legacy' => 'keep me', 'alt' => ['de' => 'New alt']], $meta);
	}

	public function testTakesACaptionAsADocumentItsJsonOrPlainText(): void
	{
		$doc = [
			'type' => 'doc',
			'content' => [[
				'type' => 'paragraph',
				'content' => [
					['type' => 'text', 'text' => 'Sudhaus', 'marks' => [['type' => 'italic']]],
				],
			]],
		];
		$input = ['de' => $doc, 'en' => json_encode($doc), 'fr' => '  plain   text ', 'it' => '<b>not html</b>'];

		$caption = Meta::apply([], ['caption' => $input], ['de', 'en', 'fr', 'it'], true)['caption'];

		$this->assertSame($doc, $caption['de']);
		$this->assertSame($doc, $caption['en']);
		$this->assertSame('plain text', $caption['fr']['content'][0]['content'][0]['text']);
		$this->assertSame('<b>not html</b>', $caption['it']['content'][0]['content'][0]['text']);
	}

	public function testDropsACaptionOutsideTheVocabulary(): void
	{
		$link = [
			'type' => 'doc',
			'content' => [[
				'type' => 'paragraph',
				'content' => [
					['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/x']]]],
				],
			]],
		];

		$this->assertArrayNotHasKey('caption', Meta::apply([], ['caption' => ['de' => $link]], ['de'], true));
	}

	public function testKeepsAnImagesTitleUnlessSubmitted(): void
	{
		$stored = ['title' => ['de' => 'Alter Titel']];

		$this->assertSame($stored, Meta::apply($stored, [], ['de'], true));
		$this->assertSame([], Meta::apply($stored, ['title' => ['de' => '']], ['de'], true));
		$this->assertSame([], Meta::apply($stored, [], ['de'], false));
	}

	public function testClampsFocalAndIgnoresItForNonImages(): void
	{
		$image = Meta::apply([], ['focal' => ['x' => 1.4, 'y' => -0.2]], ['de'], true);
		$this->assertSame(['focal' => ['x' => 1.0, 'y' => 0.0]], $image);

		$file = Meta::apply([], ['focal' => ['x' => 0.5, 'y' => 0.5]], ['de'], false);
		$this->assertSame([], $file);
	}

	public function testToleratesMalformedInput(): void
	{
		$this->assertSame([], Meta::apply([], 'not-an-array', ['de'], true));
		$this->assertSame(
			[],
			Meta::apply([], ['alt' => 'string-not-map', 'focal' => 'nope'], ['de'], true),
		);
		$this->assertSame([], Meta::apply([], ['focal' => ['x' => 'a', 'y' => 0.5]], ['de'], true));
	}
}
