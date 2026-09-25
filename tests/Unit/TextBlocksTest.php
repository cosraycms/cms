<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Asset;
use Cosray\Block\Image;
use Cosray\Block\RichText;
use Cosray\Block\Text;
use Cosray\Field;
use Cosray\Migration\TextBlocks;
use Cosray\Richtext\Renderer;
use Cosray\Richtext\Resolver;
use Cosray\Richtext\Validator;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class TextBlocksTest extends TestCase
{
	public function testTurnsATextBlockIntoARichTextBlockWithItsPlaceAndSettings(): void
	{
		$meta = ['class' => ['zxx' => 'wide'], 'padding' => ['zxx' => 'l']];
		$layout = ['col' => 3, 'row' => 2, 'colspan' => 6, 'rowspan' => 1];
		$block = TextBlocks::content(self::text('t1', ['zxx' => 'Hello'], $layout, $meta));

		$this->assertSame('t1', $block['uid']);
		$this->assertSame(RichText::class, $block['type']);
		$this->assertSame($layout, $block['layout']);
		$this->assertSame($meta, $block['meta']);
		$this->assertSame(Field\RichText::class, $block['fields']['text']['type']);
		$this->assertSame('cosray-richtext', $block['fields']['text']['format']);
		$this->assertSame(1, $block['fields']['text']['version']);
		$this->assertSame('<p>Hello</p>', $this->render($block['fields']['text']['value']['zxx']));
	}

	public function testKeepsLineBreaksAndMakesParagraphsOfWhatBlankLinesSeparate(): void
	{
		$doc = TextBlocks::doc("\r\nFirst line\r\nsecond line\r\n  \r\nNext paragraph\n\n\n\nLast <b>&</b>\n");

		$this->assertSame([], new Validator()->validate($doc));
		$this->assertSame(
			'<p>First line<br>second line</p><p>Next paragraph</p><p>Last &lt;b&gt;&amp;&lt;/b&gt;</p>',
			$this->render($doc),
		);
	}

	public function testLeavesNoDocumentForAnEmptyText(): void
	{
		$this->assertNull(TextBlocks::doc(''));
		$this->assertNull(TextBlocks::doc(" \n\t\r\n"));
	}

	public function testConvertsEachLanguageOnItsOwn(): void
	{
		$block = TextBlocks::content(self::text('t1', ['de' => 'Hallo', 'en' => '', 'es' => null]));
		$value = $block['fields']['text']['value'];

		$this->assertSame(['de', 'en', 'es'], array_keys($value));
		$this->assertSame('<p>Hallo</p>', $this->render($value['de']));
		$this->assertNull($value['en']);
		$this->assertNull($value['es']);
	}

	public function testFindsTextBlocksInSplitsAndNestedFieldsAndLeavesTheRest(): void
	{
		$image = ['uid' => 'i1', 'type' => Image::class, 'fields' => ['image' => ['value' => ['zxx' => []]]]];
		$content = [
			'title' => ['type' => Field\Text::class, 'value' => ['de' => 'Titel']],
			'body' => [
				'type' => Field\Blocks::class,
				'value' => [
					'de' => [
						$image,
						[
							'uid' => 's1',
							'layout' => ['colspan' => 12, 'rowspan' => 2],
							'blocks' => [
								self::text('p1', ['zxx' => 'In a split']),
							],
						],
						[
							'uid' => 'n1',
							'type' => 'Acme\Teaser',
							'fields' => [
								'inner' => [
									'type' => Field\Blocks::class,
									'value' => ['zxx' => [
										self::text('p2', ['zxx' => 'Nested']),
									]],
								],
							],
						],
					],
				],
			],
		];

		$converted = TextBlocks::content($content);
		$rows = $converted['body']['value']['de'];

		$this->assertSame($content['title'], $converted['title']);
		$this->assertSame($image, $rows[0]);
		$this->assertSame(RichText::class, $rows[1]['blocks'][0]['type']);
		$this->assertSame(RichText::class, $rows[2]['fields']['inner']['value']['zxx'][0]['type']);
		$this->assertSame('Acme\Teaser', $rows[2]['type']);
		$this->assertSame($converted, TextBlocks::content($converted), 'a second run changes nothing');
	}

	/**
	 * @param array<string, mixed> $value
	 * @param array<string, int> $layout
	 * @param array<string, mixed>|null $meta
	 * @return array<string, mixed>
	 */
	private static function text(
		string $uid,
		array $value,
		array $layout = ['colspan' => 12, 'rowspan' => 1],
		?array $meta = null,
	): array {
		return [
			'uid' => $uid,
			'type' => Text::class,
			'layout' => $layout,
			'fields' => ['text' => ['type' => Field\Textarea::class, 'value' => $value]],
			...($meta === null ? [] : ['meta' => $meta]),
		];
	}

	/** @param array<string, mixed>|null $doc */
	private function render(?array $doc): string
	{
		$this->assertNotNull($doc);

		return new Renderer(new class implements Resolver {
			public function asset(string $uid): ?Asset
			{
				return null;
			}

			public function nodePath(string $uid): ?string
			{
				return null;
			}

			public function localize(array $map): mixed
			{
				return $map['zxx'] ?? null;
			}
		})->render($doc);
	}
}
