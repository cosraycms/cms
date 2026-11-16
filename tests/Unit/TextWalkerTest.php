<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Block\Heading;
use Cosray\Block\Registry;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Blocks;
use Cosray\Field\Checkbox;
use Cosray\Field\Code;
use Cosray\Field\Entries;
use Cosray\Field\RichText;
use Cosray\Field\Text;
use Cosray\Field\Textarea;
use Cosray\Schema\Allows;
use Cosray\Schema\Translate;
use Cosray\Schema\TranslateMode;
use Cosray\Schema\When;
use Cosray\Tests\TestCase;
use Cosray\Text\Pointer;
use Cosray\Text\Unit;
use Cosray\Text\Walker;

final class TextWalkerTest extends TestCase
{
	public function testUnitsCoverTextFieldsRichtextBlocksAndEntriesWithPointers(): void
	{
		$content = [
			'title' => ['value' => ['de' => 'Unser Bier', 'en' => 'Our beer']],
			'summary' => ['value' => ['zxx' => "Natürlich gebraut.\nSeit 1874."]],
			'code' => ['value' => ['zxx' => 'echo "green";']],
			'body' => self::richtext('de', [
				self::paragraph([self::text('Klimaneutral '), self::text('gebraut', [['type' => 'bold']])]),
				['type' => 'codeBlock', 'content' => [self::text('nachhaltig()')]],
				[
					'type' => 'bulletList',
					'content' => [
						['type' => 'listItem', 'content' => [self::paragraph([self::text('Ökostrom')])]],
					],
				],
				self::paragraph([]),
			]),
			'blocks' => [
				'value' => [
					'en' => [
						['type' => Heading::class, 'fields' => ['text' => ['value' => ['zxx' => 'Green brewing']]]],
						[
							'type' => TextUnlisted::class,
							'fields' => ['text' => ['value' => ['zxx' => 'Not rendered']]],
						],
					],
				],
			],
			'entries' => [
				'value' => [
					'zxx' => [
						[
							'type' => TextEntry::class,
							'fields' => ['label' => ['value' => ['de' => 'Umweltfreundlich']]],
						],
					],
				],
			],
			'unknown' => ['value' => ['zxx' => 'Not declared']],
		];

		$units = new Walker(Registry::withDefaults())->texts('node-1', TextPage::class, $content);

		$this->assertSame([
			['title', 'de', '/title/value/de', 'Unser Bier'],
			['title', 'en', '/title/value/en', 'Our beer'],
			['summary', 'zxx', '/summary/value/zxx', "Natürlich gebraut.\nSeit 1874."],
			['body', 'de', '/body/value/de/content/0', 'Klimaneutral gebraut'],
			['body', 'de', '/body/value/de/content/2/content/0/content/0', 'Ökostrom'],
			['blocks', 'en', '/blocks/value/en/0/fields/text/value/zxx', 'Green brewing'],
			['entries', 'de', '/entries/value/zxx/0/fields/label/value/de', 'Umweltfreundlich'],
		], array_map(
			static fn(array $unit): array => [$unit['field'], $unit['locale'], $unit['pointer'], $unit['text']],
			$units,
		));

		foreach ($units as $unit) {
			$this->assertSame($unit['text'], Unit::text(Pointer::get($content, $unit['pointer'])['value']));
		}
	}

	public function testUnitIdsSurviveMovesAndKeepEqualTextsApart(): void
	{
		$texts = new Walker(Registry::withDefaults());
		$twice = ['body' => self::richtext('de', [
			self::paragraph([self::text('Prost')]),
			self::paragraph([self::text('Prost')]),
		])];
		$moved = ['body' => self::richtext('de', [
			self::paragraph([self::text('Neu')]),
			self::paragraph([self::text('Prost')]),
		])];

		[$first, $second] = $texts->texts('node-1', TextPage::class, $twice);
		$after = $texts->texts('node-1', TextPage::class, $moved)[1];

		$this->assertNotSame($first['id'], $second['id']);
		$this->assertSame($first['id'], $after['id']);
		$this->assertNotSame($first['pointer'], $after['pointer']);
		$this->assertNotSame($first['id'], $texts->texts('node-2', TextPage::class, $twice)[0]['id']);
	}

	public function testInactiveFieldsAreLeftOut(): void
	{
		$content = [
			'enabled' => ['value' => ['zxx' => false]],
			'teaser' => ['value' => ['zxx' => 'Hidden teaser']],
		];
		$texts = new Walker(Registry::withDefaults());

		$this->assertSame([], $texts->texts('node-1', TextConditional::class, $content));

		$content['enabled']['value']['zxx'] = true;

		$this->assertSame('Hidden teaser', $texts->texts('node-1', TextConditional::class, $content)[0]['text']);
	}

	public function testSplicingAStringReplacesTheRange(): void
	{
		$this->assertSame('Mit Ökostrom gebraut', Unit::splice('Klimaneutral gebraut', 0, 12, 'Mit Ökostrom'));
		$this->assertSame('Grüne Grüße', Unit::splice('Grüne Wiese', 6, 11, 'Grüße'));
	}

	public function testSplicingRichtextKeepsTheMarksAroundTheRange(): void
	{
		$block = self::paragraph([
			self::text('Ein '),
			self::text('klimaneutral', [['type' => 'bold']]),
			self::text(' gebrautes Bier', [['type' => 'italic']]),
		]);

		$this->assertSame(
			self::paragraph([
				self::text('Ein '),
				self::text('regional', [['type' => 'bold']]),
				self::text(' gebrautes Bier', [['type' => 'italic']]),
			]),
			Unit::splice($block, 4, 16, 'regional'),
		);

		// A range across runs takes the marks of the run it starts in.
		$this->assertSame(
			self::paragraph([
				self::text('Ein '),
				self::text('frisches', [['type' => 'bold']]),
				self::text(' Bier', [['type' => 'italic']]),
			]),
			Unit::splice($block, 4, 26, 'frisches'),
		);

		// Removing a whole run joins its neighbours when their marks agree.
		$plain = self::paragraph([
			self::text('Gut '),
			self::text('und grün', [['type' => 'bold']]),
			self::text(' hier'),
		]);
		$this->assertSame(self::paragraph([self::text('Gut hier')]), Unit::splice($plain, 4, 13, ''));
	}

	public function testSplicingRichtextDropsBreaksInsideTheRangeAndKeepsImages(): void
	{
		$image = ['type' => 'image', 'attrs' => ['uid' => 'img-1']];
		$block = self::paragraph([self::text('Grün'), ['type' => 'hardBreak'], $image, self::text('und gut')]);

		$this->assertSame("Grün\nund gut", Unit::text($block));
		$this->assertSame(
			self::paragraph([self::text('Frisch'), $image, self::text('und gut')]),
			Unit::splice($block, 0, 5, 'Frisch'),
		);
	}

	public function testSplicingRejectsRangesOutsideTheText(): void
	{
		$this->throws(RuntimeException::class, 'The range 3–9 lies outside the text');
		Unit::splice('Kurz', 3, 9, 'x');
	}

	public function testPointersEscapeAndResolveSegments(): void
	{
		$pointer = Pointer::to(['a/b', 'c~d', 0]);
		$data = ['a/b' => ['c~d' => ['first']]];

		$this->assertSame('/a~1b/c~0d/0', $pointer);
		$this->assertSame(['found' => true, 'value' => 'first'], Pointer::get($data, $pointer));
		$this->assertSame(['found' => false, 'value' => null], Pointer::get($data, '/a~1b/missing'));
		$this->assertSame(['a/b' => ['c~d' => ['second']]], Pointer::set($data, $pointer, 'second'));

		$this->throws(RuntimeException::class, "Nothing at JSON pointer '/missing'");
		Pointer::set($data, '/missing', 'x');
	}

	/** @param list<array<mixed>> $blocks */
	private static function richtext(string $locale, array $blocks): array
	{
		return [
			'format' => 'cosray-richtext',
			'version' => 1,
			'value' => [$locale => ['type' => 'doc', 'content' => $blocks]],
		];
	}

	/** @param list<array<mixed>> $content */
	private static function paragraph(array $content): array
	{
		return $content === [] ? ['type' => 'paragraph'] : ['type' => 'paragraph', 'content' => $content];
	}

	/** @param list<array<mixed>> $marks */
	private static function text(string $text, array $marks = []): array
	{
		return (
			$marks === [] ? ['type' => 'text', 'text' => $text] : ['type' => 'text', 'text' => $text, 'marks' => $marks]
		);
	}
}

class TextPage
{
	#[Translate]
	protected Text $title;

	protected Textarea $summary;
	protected Code $code;

	#[Translate]
	protected RichText $body;

	#[Translate(TranslateMode::Asymmetric)]
	protected Blocks $blocks;

	#[Allows(TextEntry::class)]
	protected Entries $entries;
}

class TextEntry
{
	#[Translate]
	protected Text $label;
}

class TextUnlisted
{
	protected Text $text;
}

class TextConditional
{
	protected Checkbox $enabled;

	#[When('enabled')]
	protected Text $teaser;
}
