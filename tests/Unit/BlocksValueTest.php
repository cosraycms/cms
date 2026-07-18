<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Asset;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Services;
use Cosray\Node\FieldOwner;
use Cosray\Storage\Storage;
use Cosray\Tests\Fixtures\Field\TestBlocks;
use Cosray\Tests\TestCase;
use Cosray\Value\Blocks as BlocksValue;
use Cosray\Value\ValueContext;

/**
 * @internal
 *
 * @coversNothing
 */
final class BlocksValueTest extends TestCase
{
	private ?Context $lastContext = null;

	private function createContext(): Context
	{
		$psrRequest = $this->psrRequest();
		$locales = new \Cosray\Locales();
		$locales->add('en', title: 'English', domains: ['www.example.com']);
		$locales->add('de', title: 'Deutsch', domains: ['www.example.de'], fallback: 'en');

		$psrRequest = $psrRequest
			->withAttribute('locales', $locales)
			->withAttribute('locale', $locales->get('en'))
			->withAttribute('defaultLocale', $locales->getDefault());

		$request = new \Celema\Core\Request($psrRequest);

		return new Context(
			$this->db(),
			$request,
			$this->config(['path.prefix' => '/cms']),
			$this->container(),
			$this->factory(),
		);
	}

	private function createOwner(Context $context): FieldOwner
	{
		return new FieldOwner($context, 'test-node');
	}

	private function createBlocksValue(array $data): BlocksValue
	{
		$context = $this->createContext();
		$this->lastContext = $context;
		$owner = $this->createOwner($context);
		$field = new TestBlocks('blocks', $owner, new ValueContext('blocks', $data));
		$field->init(Services::withDefaults());

		return $field->value();
	}

	private function seedAsset(string $uid, string $filename, ?string $mime = 'image/jpeg'): void
	{
		$this->lastContext->assets()->add(new Asset(
			uid: $uid,
			disk: 'local',
			key: Storage::key($uid, $filename),
			filename: $filename,
			kind: 'image',
			mime: $mime,
			assetsBase: '/cms/assets',
			cacheBase: '/cms/cache',
		));
	}

	private function imageBlocks(array $item, int $colspan = 6): BlocksValue
	{
		return $this->createBlocksValue([
			'columns' => 12,
			'value' => [
				['type' => 'image', 'value' => [$item], 'colspan' => $colspan, 'rowspan' => 1],
			],
		]);
	}

	private function imagesBlocks(array $items): BlocksValue
	{
		return $this->createBlocksValue([
			'columns' => 12,
			'value' => [
				['type' => 'images', 'value' => $items, 'colspan' => 12, 'rowspan' => 1],
			],
		]);
	}

	public function testUnwrapReturnsColumnsAndPreparedData(): void
	{
		$blocks = $this->createBlocksValue([
			'columns' => 12,
			'value' => [
				'en' => [
					['type' => 'text', 'value' => 'Hello', 'colspan' => 12, 'rowspan' => 1],
				],
			],
		]);

		$unwrapped = $blocks->unwrap();
		$this->assertSame(12, $unwrapped['columns']);
		$this->assertIsIterable($unwrapped['data']);
	}

	public function testHasImageDetectsImageItems(): void
	{
		$blocks = $this->createBlocksValue([
			'columns' => 12,
			'value' => [
				['type' => 'text', 'value' => 'Hello', 'colspan' => 12, 'rowspan' => 1],
				['type' => 'image', 'files' => [['file' => 'test.jpg']], 'colspan' => 12, 'rowspan' => 1],
			],
		]);

		$this->assertTrue($blocks->hasImage());
	}

	public function testExcerptReturnsEmptyWhenNoHtml(): void
	{
		$blocks = $this->createBlocksValue([
			'columns' => 12,
			'value' => [
				['type' => 'text', 'value' => 'Hello', 'colspan' => 12, 'rowspan' => 1],
			],
		]);

		$this->assertSame('', $blocks->excerpt());
	}

	public function testIssetReturnsFalseForEmptyValue(): void
	{
		$blocks = $this->createBlocksValue([
			'columns' => 12,
			'value' => [],
		]);

		$this->assertFalse($blocks->isset());
	}

	public function testImageBlockRendersSrcsetLadder(): void
	{
		$blocks = $this->imageBlocks(['uid' => 'blockimg12345', 'alt' => ['en' => 'A "quoted" alt']]);
		$this->seedAsset('blockimg12345', 'Sun & Sea.jpg');

		$html = $blocks->render();

		$this->assertStringContainsString('class="cms-image cms-colspan-6 cms-rowspan-1"', $html);
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
		$blocks = $this->imageBlocks(['uid' => 'blockimg12345'], colspan: 4);
		$this->seedAsset('blockimg12345', 'pic.jpg');

		$html = $blocks->render(sizes: '(min-width: 60rem) {pct}vw, 100vw');

		$this->assertStringContainsString('sizes="(min-width: 60rem) 33vw, 100vw"', $html);
	}

	public function testImageBlockSingleSizeEmitsPlainSrc(): void
	{
		$blocks = $this->imageBlocks(['uid' => 'blockimg12345']);
		$this->seedAsset('blockimg12345', 'pic.jpg');

		$html = $blocks->render(imageSizes: ['block-thumb']);

		$this->assertStringContainsString('src="/cms/cache/bl/blockimg12345/pic-block-thumb.jpg"', $html);
		$this->assertStringNotContainsString('srcset', $html);
		$this->assertStringNotContainsString('sizes=', $html);
	}

	public function testImageBlockNonWidthSrcsetEntryThrows(): void
	{
		$blocks = $this->imageBlocks(['uid' => 'blockimg12345']);
		$this->seedAsset('blockimg12345', 'pic.jpg');

		$this->throws(RuntimeException::class, "srcset entry 'block-thumb' must use the `width` mode");
		$blocks->render(imageSizes: ['block', 'block-thumb']);
	}

	public function testImageBlockUnknownSizeThrows(): void
	{
		$blocks = $this->imageBlocks(['uid' => 'blockimg12345']);
		$this->seedAsset('blockimg12345', 'pic.jpg');

		$this->throws(RuntimeException::class, "Unknown media size 'nope'");
		$blocks->render(imageSizes: ['nope']);
	}

	public function testImageBlockEmptySizesArgThrows(): void
	{
		$blocks = $this->imageBlocks(['uid' => 'blockimg12345']);
		$this->seedAsset('blockimg12345', 'pic.jpg');

		$this->throws(RuntimeException::class, '`imageSizes` must be a non-empty list');
		$blocks->render(imageSizes: []);
	}

	public function testImageBlockWithDanglingUidRendersNothing(): void
	{
		$blocks = $this->imageBlocks(['uid' => 'missingasset1']);

		$html = $blocks->render();

		// The empty block must not occupy a grid cell either.
		$this->assertStringNotContainsString('cms-image', $html);
		$this->assertStringNotContainsString('<img', $html);
	}

	public function testImageBlockSvgKeepsOriginalUrl(): void
	{
		$blocks = $this->imageBlocks(['uid' => 'blockimg12345']);
		$this->seedAsset('blockimg12345', 'logo.svg', 'image/svg+xml');

		$html = $blocks->render();

		$this->assertStringContainsString('src="/cms/assets/bl/blockimg12345/logo.svg"', $html);
		$this->assertStringNotContainsString('srcset', $html);
		$this->assertStringNotContainsString('/cms/cache/', $html);
	}

	public function testImagesBlockRendersThumbsAndSkipsDanglingItems(): void
	{
		$blocks = $this->imagesBlocks([
			['uid' => 'galleryimg123', 'alt' => ['en' => 'One']],
			['uid' => 'missingasset1'],
		]);
		$this->seedAsset('galleryimg123', 'one.jpg');

		$html = $blocks->render();

		$this->assertSame(1, substr_count($html, 'cms-blocks-images-image'));
		$this->assertStringContainsString(
			'src="/cms/cache/ga/galleryimg123/one-block-thumb.jpg"',
			$html,
		);
		$this->assertStringContainsString('alt="One"', $html);
	}

	public function testImagesBlockThumbSizeArg(): void
	{
		$blocks = $this->imagesBlocks([['uid' => 'galleryimg123']]);
		$this->seedAsset('galleryimg123', 'one.jpg');

		$html = $blocks->render(thumbSize: 'thumb');

		$this->assertStringContainsString('src="/cms/cache/ga/galleryimg123/one-thumb.jpg"', $html);
	}

	public function testImagesBlockUnknownThumbSizeThrows(): void
	{
		$blocks = $this->imagesBlocks([['uid' => 'galleryimg123']]);
		$this->seedAsset('galleryimg123', 'one.jpg');

		$this->throws(RuntimeException::class, "Unknown media size 'nope'");
		$blocks->render(thumbSize: 'nope');
	}
}
