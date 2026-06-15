<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Field;
use Cosray\Migration\NodeContentNormalizer;
use Cosray\Tests\TestCase;
use Cosray\Uid;

final class NodeContentNormalizerTest extends TestCase
{
	public function testNormalizesLegacyScalarAndMeta(): void
	{
		$normalized = $this->normalizer()->normalize([
			'title' => [
				'type' => 'text',
				'value' => 'Hello',
				'class' => 'hero',
			],
		]);

		$this->assertSame(Field\Text::class, $normalized['title']['type']);
		$this->assertSame(['zxx' => 'Hello'], $normalized['title']['value']);
		$this->assertSame(['zxx' => 'hero'], $normalized['title']['meta']['class']);
	}

	public function testConvertsPictureToSingleImage(): void
	{
		$normalized = $this->normalizer()->normalize([
			'hero' => [
				'type' => 'picture',
				'files' => [
					['file' => 'hero.png', 'alt' => 'PNG'],
					['file' => 'hero.webp', 'alt' => 'WEBP'],
				],
			],
		]);

		$this->assertSame(Field\Image::class, $normalized['hero']['type']);
		$this->assertSame('hero.webp', $normalized['hero']['value']['zxx'][0]['file']);
		$this->assertSame(['zxx' => 'WEBP'], $normalized['hero']['value']['zxx'][0]['meta']['alt']);
		$this->assertCount(1, $normalized['hero']['value']['zxx']);
	}

	public function testNormalizesBlocksAndEntriesRecursively(): void
	{
		$normalized = $this->normalizer()->normalize([
			'content' => [
				'type' => 'blocks',
				'columns' => 12,
				'value' => [
					[
						'type' => 'youtube',
						'id' => 'abc123',
						'aspectRatioX' => 16,
						'aspectRatioY' => 9,
						'colspan' => 12,
						'rowspan' => 1,
					],
				],
			],
			'items' => [
				'type' => 'entries',
				'value' => [
					[
						'type' => 'App\\Entry',
						'value' => [
							'name' => ['type' => 'text', 'value' => 'Jane'],
						],
					],
				],
			],
		]);

		$block = $normalized['content']['value']['zxx'][0];
		$this->assertSame(Field\Blocks::class, $normalized['content']['type']);
		$this->assertSame(['zxx' => 12], $normalized['content']['meta']['columns']);
		$this->assertSame(['zxx' => 'abc123'], $block['value']);
		$this->assertSame(['zxx' => 16], $block['meta']['aspectRatioX']);

		$entry = $normalized['items']['value']['zxx'][0];
		$this->assertSame(Field\Entries::class, $normalized['items']['type']);
		$this->assertMatchesRegularExpression('/^[ab]{4}$/', $entry['uid']);
		$this->assertSame(Field\Text::class, $entry['fields']['name']['type']);
		$this->assertSame(['zxx' => 'Jane'], $entry['fields']['name']['value']);
	}

	private function normalizer(): NodeContentNormalizer
	{
		return new NodeContentNormalizer(new Uid('ab', 4));
	}
}
