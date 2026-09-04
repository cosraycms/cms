<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Asset;
use Cosray\Block as Builtin;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Blocks;
use Cosray\Field\Field;
use Cosray\Field\Services;
use Cosray\Node\FieldOwner;
use Cosray\Richtext\Envelope;
use Cosray\Schema\TranslateMode;
use Cosray\Storage\Storage;
use Cosray\Tests\Fixtures\Block\QuoteBlock;
use Cosray\Tests\TestCase;
use Cosray\Value\Block;
use Cosray\Value\Blocks as BlocksValue;
use Cosray\Value\Image;
use Cosray\Value\ValueContext;

/**
 * @internal
 *
 * @coversNothing
 */
final class BlocksValueTest extends TestCase
{
	private ?Context $lastContext = null;

	private function createContext(string $locale = 'en'): Context
	{
		$psrRequest = $this->psrRequest();
		$locales = new \Cosray\Locales();
		$locales->add('en', title: 'English', domains: ['www.example.com']);
		$locales->add('de', title: 'Deutsch', domains: ['www.example.de'], fallback: 'en');

		$psrRequest = $psrRequest
			->withAttribute('locales', $locales)
			->withAttribute('locale', $locales->get($locale))
			->withAttribute('defaultLocale', $locales->getDefault());

		return new Context(
			$this->db(),
			new \Celema\Core\Request($psrRequest),
			$this->config(['path.prefix' => '/cms']),
			$this->container(),
			$this->factory(),
		);
	}

	/** @param list<array>|array<string, list<array>> $rows a list, or per-locale lists */
	private function createBlocksValue(
		array $rows,
		?TranslateMode $mode = null,
		string $locale = 'en',
		int $columns = 12,
	): BlocksValue {
		$context = $this->createContext($locale);
		$this->lastContext = $context;
		$owner = new FieldOwner($context, 'test-node');
		$value = array_is_list($rows) ? [Field::NEUTRAL_LOCALE => $rows] : $rows;
		$field = new Blocks('blocks', $owner, new ValueContext('blocks', ['type' => Blocks::class, 'value' => $value]));
		$field->init(Services::withDefaults());
		$field
			->columns($columns, min(2, $columns))
			->translate($mode)
			->allow(...[...$field->allowedBlockTypes(), QuoteBlock::class]);

		return $field->value();
	}

	private function row(string $type, array $fields, array $layout = [], array $meta = [], string $uid = 'b1'): array
	{
		$row = ['uid' => $uid, 'type' => $type, 'layout' => $layout, 'fields' => $fields];

		if ($meta !== []) {
			$row['meta'] = $meta;
		}

		return $row;
	}

	private function text(string $text, array $layout = [], array $meta = [], string $uid = 'b1'): array
	{
		$value = is_array($text) ? $text : [Field::NEUTRAL_LOCALE => $text];

		return $this->row(
			Builtin\Text::class,
			[
				'text' => ['type' => \Cosray\Field\Textarea::class, 'value' => $value],
			],
			$layout,
			$meta,
			$uid,
		);
	}

	private function image(array $item, int $span = 6, string $uid = 'b1'): array
	{
		return $this->row(
			Builtin\Image::class,
			[
				'image' => ['type' => \Cosray\Field\Image::class, 'value' => [Field::NEUTRAL_LOCALE => [$item]]],
			],
			['span' => $span, 'rows' => 1, 'indent' => 0],
			uid: $uid,
		);
	}

	private function images(array $items, string $uid = 'b1'): array
	{
		return $this->row(
			Builtin\Images::class,
			[
				'images' => ['type' => \Cosray\Field\Image::class, 'value' => [Field::NEUTRAL_LOCALE => $items]],
			],
			['span' => 12, 'rows' => 1, 'indent' => 0],
			uid: $uid,
		);
	}

	private function richtext(string $text, string $uid = 'b1'): array
	{
		return $this->row(
			Builtin\RichText::class,
			[
				'text' => [
					'type' => \Cosray\Field\RichText::class,
					'format' => Envelope::FORMAT,
					'version' => Envelope::VERSION,
					'value' => [
						Field::NEUTRAL_LOCALE => [
							'type' => 'doc',
							'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]],
						],
					],
				],
			],
			['span' => 12, 'rows' => 1, 'indent' => 0],
			uid: $uid,
		);
	}

	private function seedAsset(string $uid, string $filename, ?string $mime = 'image/jpeg'): void
	{
		$this->lastContext->assets()->add(new Asset(
			uid: $uid,
			disk: 'local',
			key: Storage::key($uid, $filename),
			filename: $filename,
			mime: $mime,
			assetsBase: '/cms/assets',
			cacheBase: '/cms/cache',
		));
	}

	public function testRendersTheContract(): void
	{
		$blocks = $this->createBlocksValue([
			$this->text(
				"Hello\nWorld",
				['span' => 8, 'rows' => 2, 'indent' => 2],
				[
					'class' => ['zxx' => 'hero'],
					'id' => ['zxx' => 'intro'],
				],
			),
		]);

		$this->assertSame(
			'<div class="cms-blocks" data-columns="12" data-responsive="stack" style="--columns: 12">'
				. '<div class="cms-block hero" id="intro" data-type="text" data-span="8" data-rows="2" data-indent="2"'
				. ' style="--span: 8; --rows: 2; --indent: 2; --reserved: 10">'
				. "Hello<br />\nWorld"
				. '</div></div>',
			$blocks->render(),
		);
		$this->assertSame($blocks->render(), (string) $blocks);
	}

	public function testRenderArgs(): void
	{
		$html = $this->createBlocksValue([$this->text('Hi', ['span' => 12, 'rows' => 1, 'indent' => 0])])
			->render(tag: 'section', prefix: 'x', class: 'page');

		$this->assertStringStartsWith('<section class="x-blocks page" data-columns="12"', $html);
		$this->assertStringContainsString('<div class="x-block" data-type="text"', $html);
		$this->assertStringEndsWith('</section>', $html);
	}

	public function testRenderRejectsInvalidTagAndPrefix(): void
	{
		$blocks = $this->createBlocksValue([]);

		try {
			$blocks->render(tag: 'div onclick="x"');
			$this->fail('A tag with attributes must be rejected');
		} catch (RuntimeException $e) {
			$this->assertStringContainsString('`tag`', $e->getMessage());
		}

		$this->throws(RuntimeException::class, '`prefix`');
		$blocks->render(prefix: '1cms');
	}

	public function testEscapesEveryGeneratedAttribute(): void
	{
		$blocks = $this->createBlocksValue([
			$this->text(
				'x',
				['span' => 12, 'rows' => 1, 'indent' => 0],
				[
					'class' => ['zxx' => '"><img src=x onerror=alert(1)>'],
					'id' => ['zxx' => 'x" onmouseover="alert(1)'],
				],
			),
		]);

		$html = $blocks->render(class: 'page" onmouseover="alert(1)');

		$this->assertStringNotContainsString('<img', $html);
		$this->assertStringNotContainsString('" onmouseover="', $html);
		$this->assertStringContainsString('class="cms-blocks page&quot; onmouseover=&quot;alert(1)"', $html);
		$this->assertStringContainsString('class="cms-block &quot;&gt;&lt;img src=x onerror=alert(1)&gt;"', $html);
		$this->assertStringContainsString('id="x&quot; onmouseover=&quot;alert(1)"', $html);
	}

	public function testYoutubeIdIsEscaped(): void
	{
		$blocks = $this->createBlocksValue([
			$this->row(
				Builtin\Youtube::class,
				[
					'video' => ['type' => \Cosray\Field\Youtube::class, 'value' => ['zxx' => 'abc" onload="alert(1)']],
				],
				['span' => 12, 'rows' => 1, 'indent' => 0],
			),
		]);

		$html = $blocks->render();

		$this->assertStringContainsString('embed/abc&quot; onload=&quot;alert(1)"', $html);
		$this->assertStringNotContainsString('" onload="', $html);
	}

	public function testReadersClampTheLayout(): void
	{
		$html = $this->createBlocksValue([
			$this->text('x', ['span' => 30, 'rows' => 9, 'indent' => 4]),
		])->render();

		$this->assertStringContainsString('data-span="12" data-rows="6" data-indent="0"', $html);

		$stacked = $this->createBlocksValue([$this->text('x', ['span' => 6, 'rows' => 1, 'indent' => 3])], columns: 1);
		$html = $stacked->render();

		$this->assertStringContainsString('data-columns="1"', $html);
		$this->assertStringContainsString('data-span="1" data-rows="1" data-indent="0"', $html);
		$this->assertSame(['span' => 1, 'rows' => 1, 'indent' => 0], $stacked->first()?->layout()->array());
	}

	public function testEmptyRenderEmitsNoElement(): void
	{
		$html = $this->createBlocksValue([$this->image(['uid' => 'missingasset1'])])->render();

		$this->assertSame(
			'<div class="cms-blocks" data-columns="12" data-responsive="stack" style="--columns: 12"></div>',
			$html,
		);
	}

	public function testIterationAndAccessors(): void
	{
		$blocks = $this->createBlocksValue([
			$this->text('One', ['span' => 4, 'rows' => 1, 'indent' => 0], uid: 'b1'),
			$this->text('Two', ['span' => 8, 'rows' => 1, 'indent' => 0], uid: 'b2'),
		]);

		$this->assertTrue($blocks->isset());
		$this->assertSame(2, $blocks->count());
		$this->assertSame(12, $blocks->columns());
		$this->assertSame(['b1', 'b2'], array_map(static fn(Block $block): ?string => $block->uid(), [...$blocks]));
		$this->assertSame('One', $blocks->first()?->text->unwrap());
		$this->assertSame('Two', $blocks->last()?->text->unwrap());
		$this->assertSame('b2', $blocks->get(1)?->uid());
		$this->assertNull($blocks->get(2));
		$this->assertSame(Builtin\Text::class, $blocks->first()?->type);
		$this->assertSame('text', $blocks->first()?->handle());
		$this->assertSame(4, $blocks->first()?->layout()->span);
	}

	public function testUnwrapAndJson(): void
	{
		$blocks = $this->createBlocksValue([
			$this->text('Hello', ['span' => 6, 'rows' => 1, 'indent' => 0], ['class' => ['zxx' => 'wide']]),
		]);

		$unwrapped = $blocks->unwrap();
		$this->assertSame(12, $unwrapped['columns']);
		$this->assertSame(
			[
				'uid' => 'b1',
				'type' => Builtin\Text::class,
				'handle' => 'text',
				'layout' => ['span' => 6, 'rows' => 1, 'indent' => 0],
				'fields' => ['text' => 'Hello'],
				'meta' => ['class' => ['zxx' => 'wide']],
			],
			$unwrapped['blocks'][0],
		);

		$json = json_decode(json_encode($blocks->json(), JSON_THROW_ON_ERROR), true);
		$this->assertSame('Hello', $json['blocks'][0]['fields']['text']);
		$this->assertSame([], $this->createBlocksValue([])->json()['blocks']);
	}

	public function testUnknownAndLegacyRowsAreSkipped(): void
	{
		$blocks = $this->createBlocksValue([
			['type' => 'richtext', 'colspan' => 12, 'rowspan' => 1, 'value' => ['zxx' => 'old']],
			'junk',
			['uid' => 'b2', 'type' => 'App\\Nope', 'fields' => []],
		]);

		$this->assertFalse($blocks->isset());
		$this->assertSame(0, $blocks->count());
		$this->assertNull($blocks->first());
	}

	public function testPerLocaleListsFollowTheFallbackChain(): void
	{
		$rows = ['en' => [$this->text('English')], 'de' => []];

		$german = $this->createBlocksValue($rows, TranslateMode::Asymmetric, locale: 'de');
		$this->assertStringContainsString('>English<', $german->render());

		$rows['de'] = [$this->text('Deutsch', uid: 'b2')];
		$german = $this->createBlocksValue($rows, TranslateMode::Asymmetric, locale: 'de');
		$this->assertStringContainsString('>Deutsch<', $german->render());
		$this->assertStringNotContainsString('English', $german->render());
	}

	public function testSymmetricListsTranslateTheirSubFields(): void
	{
		$rows = [
			$this->row(
				Builtin\Text::class,
				[
					'text' => ['type' => \Cosray\Field\Textarea::class, 'value' => ['en' => 'Hello', 'de' => 'Hallo']],
				],
				['span' => 12, 'rows' => 1, 'indent' => 0],
			),
		];

		$this->assertStringContainsString(
			'>Hello<',
			$this->createBlocksValue($rows, TranslateMode::Symmetric)->render(),
		);
		$this->assertStringContainsString(
			'>Hallo<',
			$this->createBlocksValue($rows, TranslateMode::Symmetric, locale: 'de')->render(),
		);
	}

	public function testBlockRendersStandalone(): void
	{
		$blocks = $this->createBlocksValue([$this->text('Solo', ['span' => 3, 'rows' => 1, 'indent' => 0])]);
		$block = $blocks->first();

		$this->assertInstanceOf(Block::class, $block);
		$this->assertSame(
			'<div class="cms-block" data-type="text" data-span="3" data-rows="1" data-indent="0"'
				. ' style="--span: 3; --rows: 1; --indent: 0; --reserved: 3">Solo</div>',
			(string) $block,
		);
		$this->assertStringStartsWith('<div class="x-block"', $block->render(prefix: 'x'));
	}

	public function testPluginBlockRenders(): void
	{
		$html = $this->createBlocksValue([
			$this->row(
				QuoteBlock::class,
				[
					'text' => ['type' => \Cosray\Field\Textarea::class, 'value' => ['zxx' => 'Less <is> more']],
					'source' => ['type' => \Cosray\Field\Text::class, 'value' => ['zxx' => 'Mies']],
				],
				['span' => 12, 'rows' => 1, 'indent' => 0],
			),
		])->render();

		$this->assertStringContainsString(
			'data-type="quote-block"',
			$html,
		);
		$this->assertStringContainsString(
			'<blockquote><p>Less &lt;is&gt; more</p><cite>Mies</cite></blockquote>',
			$html,
		);
	}

	public function testImageBlockRendersSrcsetLadder(): void
	{
		$blocks = $this->createBlocksValue([
			$this->image(['uid' => 'blockimg12345', 'meta' => ['alt' => ['en' => 'A "quoted" alt']]]),
		]);
		$this->seedAsset('blockimg12345', 'Sun & Sea.jpg');

		$html = $blocks->render();

		$this->assertStringContainsString('class="cms-block" data-type="image" data-span="6"', $html);
		$this->assertStringContainsString(
			'src="/cms/cache/bl/blockimg12345/sun-sea-block.jpg"',
			$html,
		);
		$this->assertStringContainsString(
			'srcset="/cms/cache/bl/blockimg12345/sun-sea-block-sm.jpg 480w, '
				. '/cms/cache/bl/blockimg12345/sun-sea-block.jpg 960w, '
				. '/cms/cache/bl/blockimg12345/sun-sea-block-lg.jpg 1440w"',
			$html,
		);
		$this->assertStringContainsString('sizes="(min-width: 48rem) 50vw, 100vw"', $html);
		$this->assertStringContainsString('alt="A &quot;quoted&quot; alt"', $html);
		$this->assertStringContainsString(
			'data-path-original="/cms/assets/bl/blockimg12345/sun-sea.jpg"',
			$html,
		);
	}

	public function testImageBlockSizesTemplateArg(): void
	{
		$blocks = $this->createBlocksValue([$this->image(['uid' => 'blockimg12345'], span: 4)]);
		$this->seedAsset('blockimg12345', 'pic.jpg');

		$html = $blocks->render(sizes: '(min-width: 60rem) {pct}vw, 100vw');

		$this->assertStringContainsString('sizes="(min-width: 60rem) 33vw, 100vw"', $html);
	}

	public function testImageBlockSingleSizeEmitsPlainSrc(): void
	{
		$blocks = $this->createBlocksValue([$this->image(['uid' => 'blockimg12345'])]);
		$this->seedAsset('blockimg12345', 'pic.jpg');

		$html = $blocks->render(imageSizes: ['block-thumb']);

		$this->assertStringContainsString('src="/cms/cache/bl/blockimg12345/pic-block-thumb.jpg"', $html);
		$this->assertStringNotContainsString('srcset', $html);
		$this->assertStringNotContainsString('sizes=', $html);
	}

	public function testImageBlockNonWidthSrcsetEntryThrows(): void
	{
		$blocks = $this->createBlocksValue([$this->image(['uid' => 'blockimg12345'])]);
		$this->seedAsset('blockimg12345', 'pic.jpg');

		$this->throws(RuntimeException::class, "srcset entry 'block-thumb' must use the `width` mode");
		$blocks->render(imageSizes: ['block', 'block-thumb']);
	}

	public function testImageBlockUnknownSizeThrows(): void
	{
		$blocks = $this->createBlocksValue([$this->image(['uid' => 'blockimg12345'])]);
		$this->seedAsset('blockimg12345', 'pic.jpg');

		$this->throws(RuntimeException::class, "Unknown media size 'nope'");
		$blocks->render(imageSizes: ['nope']);
	}

	public function testImageBlockEmptySizesArgThrows(): void
	{
		$blocks = $this->createBlocksValue([$this->image(['uid' => 'blockimg12345'])]);
		$this->seedAsset('blockimg12345', 'pic.jpg');

		$this->throws(RuntimeException::class, '`imageSizes` must be a non-empty list');
		$blocks->render(imageSizes: []);
	}

	public function testImageBlockSvgKeepsOriginalUrl(): void
	{
		$blocks = $this->createBlocksValue([$this->image(['uid' => 'blockimg12345'])]);
		$this->seedAsset('blockimg12345', 'logo.svg', 'image/svg+xml');

		$html = $blocks->render();

		$this->assertStringContainsString('src="/cms/assets/bl/blockimg12345/logo.svg"', $html);
		$this->assertStringNotContainsString('srcset', $html);
		$this->assertStringNotContainsString('/cms/cache/', $html);
	}

	public function testImagesBlockRendersThumbsAndSkipsDanglingItems(): void
	{
		$blocks = $this->createBlocksValue([
			$this->images([
				['uid' => 'galleryimg123', 'meta' => ['alt' => ['en' => 'One']]],
				['uid' => 'missingasset1'],
			]),
		]);
		$this->seedAsset('galleryimg123', 'one.jpg');

		$html = $blocks->render();

		$this->assertStringContainsString('data-type="images"', $html);
		$this->assertSame(1, substr_count($html, 'cms-blocks-images-image'));
		$this->assertStringContainsString(
			'src="/cms/cache/ga/galleryimg123/one-block-thumb.jpg"',
			$html,
		);
		$this->assertStringContainsString('alt="One"', $html);
	}

	public function testImagesBlockThumbSizeArg(): void
	{
		$blocks = $this->createBlocksValue([$this->images([['uid' => 'galleryimg123']])]);
		$this->seedAsset('galleryimg123', 'one.jpg');

		$html = $blocks->render(thumbSize: 'thumb');

		$this->assertStringContainsString('src="/cms/cache/ga/galleryimg123/one-thumb.jpg"', $html);
	}

	public function testImagesBlockUnknownThumbSizeThrows(): void
	{
		$blocks = $this->createBlocksValue([$this->images([['uid' => 'galleryimg123']])]);
		$this->seedAsset('galleryimg123', 'one.jpg');

		$this->throws(RuntimeException::class, "Unknown media size 'nope'");
		$blocks->render(thumbSize: 'nope');
	}

	public function testVideoBlockRendersTheAssetAndSkipsDanglingOnes(): void
	{
		$video = fn(string $uid): array => $this->row(
			Builtin\Video::class,
			[
				'video' => [
					'type' => \Cosray\Field\Video::class,
					'value' => [Field::NEUTRAL_LOCALE => [['uid' => $uid]]],
				],
			],
			['span' => 12, 'rows' => 1, 'indent' => 0],
		);
		$blocks = $this->createBlocksValue([$video('videoasset001')]);
		$this->seedAsset('videoasset001', 'clip.mp4', 'video/mp4');

		$html = $blocks->render();

		$this->assertStringContainsString('data-type="video"', $html);
		$this->assertStringContainsString('<video controls><source src="', $html);
		$this->assertStringContainsString('/cms/assets/vi/videoasset001/clip.mp4" type="video/mp4"/></video>', $html);
		$this->assertStringNotContainsString(
			'<div class="cms-block"',
			$this->createBlocksValue([$video('missingasset1')])->render(),
		);
	}

	public function testImageHelpers(): void
	{
		$blocks = $this->createBlocksValue([
			$this->text('Intro', ['span' => 12, 'rows' => 1, 'indent' => 0], uid: 'b1'),
			$this->image(['uid' => 'blockimg12345'], uid: 'b2'),
			$this->images([['uid' => 'galleryimg123'], ['uid' => 'galleryimg124']], uid: 'b3'),
			$this->image(['uid' => 'blockimg12346'], uid: 'b4'),
		]);
		$this->seedAsset('blockimg12345', 'one.jpg');
		$this->seedAsset('blockimg12346', 'two.jpg');
		$this->seedAsset('galleryimg123', 'three.jpg');
		$this->seedAsset('galleryimg124', 'four.jpg');

		$this->assertTrue($blocks->hasImage());
		$this->assertTrue($blocks->hasImage(2));
		$this->assertFalse($blocks->hasImage(3));
		$this->assertInstanceOf(Image::class, $blocks->image());
		$this->assertSame('one.jpg', $blocks->image()?->filename());
		$this->assertSame('two.jpg', $blocks->image(2)?->filename());
		$this->assertNull($blocks->image(3));
		$this->assertSame(
			['one.jpg', 'three.jpg', 'four.jpg', 'two.jpg'],
			array_map(static fn(Image $image): string => $image->filename(), [...$blocks->images()]),
		);
	}

	public function testImagesOfEveryLocale(): void
	{
		$blocks = $this->createBlocksValue([
			'en' => [$this->image(['uid' => 'blockimg12345'])],
			'de' => [$this->image(['uid' => 'blockimg12346'])],
		], TranslateMode::Asymmetric);
		$this->seedAsset('blockimg12345', 'one.jpg');
		$this->seedAsset('blockimg12346', 'two.jpg');

		$this->assertSame(
			['one.jpg'],
			array_map(static fn(Image $image): string => $image->filename(), [...$blocks->images()]),
		);
		$this->assertSame(
			['one.jpg', 'two.jpg'],
			array_map(static fn(Image $image): string => $image->filename(), [...$blocks->images(all: true)]),
		);
	}

	public function testExcerptReadsTheRichtextBlock(): void
	{
		$blocks = $this->createBlocksValue([
			$this->text('Plain', ['span' => 12, 'rows' => 1, 'indent' => 0], uid: 'b1'),
			$this->richtext('One two three four five', uid: 'b2'),
			$this->richtext('Second', uid: 'b3'),
		]);

		$this->assertSame('One two three …', $blocks->excerpt(3));
		$this->assertSame('Second', $blocks->excerpt(index: 2));
		$this->assertSame('', $blocks->excerpt(index: 3));
		$this->assertSame('', $this->createBlocksValue([])->excerpt());
	}
}
