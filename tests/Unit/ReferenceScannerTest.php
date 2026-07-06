<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Field\Blocks;
use Cosray\Field\Entries;
use Cosray\Field\Image;
use Cosray\Field\Reference;
use Cosray\Field\RichText;
use Cosray\References\Scanner;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ReferenceScannerTest extends TestCase
{
	public function testCollectsEveryCarrierKind(): void
	{
		$refs = new Scanner()->scan([
			'title' => ['type' => 'text', 'value' => ['de' => 'Titel']],
			'hero' => [
				'type' => Image::class,
				'value' => ['zxx' => [['uid' => 'img-hero'], ['uid' => 'img-dup']]],
			],
			'download' => [
				'type' => 'file',
				'value' => ['zxx' => [['uid' => 'file-doc', 'meta' => ['zxx' => []]]]],
			],
			'body' => [
				'type' => RichText::class,
				'format' => 'cosray-richtext',
				'version' => 1,
				'value' => [
					'de' => $this->doc([
						$this->paragraph([
							$this->text('Datei', [['type' => 'link', 'attrs' => ['asset' => 'file-linked']]]),
							$this->text('Seite', [['type' => 'link', 'attrs' => ['node' => 'node-target']]]),
							['type' => 'image', 'attrs' => ['uid' => 'img-inline']],
						]),
					]),
					'en' => null,
				],
			],
			'blocks' => [
				'type' => Blocks::class,
				'value' => [
					'zxx' => [
						['type' => 'image', 'value' => [['uid' => 'img-block']]],
						['type' => 'images', 'value' => [['uid' => 'img-dup'], ['uid' => 'img-gallery']]],
						['type' => 'video', 'value' => [['uid' => 'vid-block']]],
						[
							'type' => 'richtext',
							'format' => 'cosray-richtext',
							'version' => 1,
							'value' => ['zxx' => $this->doc([
								$this->paragraph([
									$this->text('Link', [['type' => 'link', 'attrs' => ['node' => 'node-from-block']]]),
								]),
							])],
						],
						['type' => 'text', 'value' => ['zxx' => 'plain']],
					],
				],
			],
			'staff' => [
				'type' => Entries::class,
				'value' => [
					'zxx' => [
						[
							'uid' => 'entry-own-uid',
							'type' => 'App\Node\Entries\Staff',
							'fields' => [
								'photo' => [
									'type' => Image::class,
									'value' => ['zxx' => [['uid' => 'img-entry']]],
								],
							],
						],
					],
				],
			],
		]);

		$this->assertSame(
			[
				'file-doc',
				'file-linked',
				'img-block',
				'img-dup',
				'img-entry',
				'img-gallery',
				'img-hero',
				'img-inline',
				'vid-block',
			],
			$refs['assets'],
		);
		$this->assertSame(['node-from-block', 'node-target'], $refs['nodes']);
	}

	public function testCollectsReferenceFieldTargets(): void
	{
		$refs = new Scanner()->scan([
			'related' => [
				'type' => Reference::class,
				'value' => ['zxx' => [['uid' => 'ref-b'], ['uid' => 'ref-a'], ['uid' => '']]],
			],
			'hero' => [
				'type' => Image::class,
				'value' => ['zxx' => [['uid' => 'img-hero']]],
			],
			'staff' => [
				'type' => Entries::class,
				'value' => [
					'zxx' => [
						[
							'uid' => 'entry-own-uid',
							'type' => 'App\Node\Entries\Staff',
							'fields' => [
								'author' => [
									'type' => Reference::class,
									'value' => ['zxx' => [['uid' => 'ref-nested']]],
								],
							],
						],
					],
				],
			],
		]);

		// Reference targets land in nodes; the media field stays an asset.
		$this->assertSame(['img-hero'], $refs['assets']);
		$this->assertSame(['ref-a', 'ref-b', 'ref-nested'], $refs['nodes']);
	}

	public function testEntryUidsAreNotReferences(): void
	{
		$refs = new Scanner()->scan([
			'staff' => [
				'type' => Entries::class,
				'value' => [
					'zxx' => [
						['uid' => 'embedded-entry', 'type' => 'App\Staff', 'fields' => []],
					],
				],
			],
		]);

		$this->assertSame([], $refs['assets']);
		$this->assertSame([], $refs['nodes']);
	}

	public function testToleratesMalformedContent(): void
	{
		$refs = new Scanner()->scan([
			'junk' => 'not a field',
			'untyped' => ['value' => ['zxx' => [['uid' => 'ignored']]]],
			'unknown' => ['type' => 'no-such-type', 'value' => ['zxx' => [['uid' => 'ignored']]]],
			'hero' => [
				'type' => Image::class,
				'value' => [
					'zxx' => [
						['uid' => ''],
						['uid' => 42],
						['meta' => []],
						'not-an-item',
						['uid' => 'img-valid'],
					],
				],
			],
			'blocks' => [
				'type' => Blocks::class,
				'value' => ['zxx' => ['not-a-block', ['type' => 'image', 'value' => 'nope']]],
			],
		]);

		$this->assertSame(['img-valid'], $refs['assets']);
		$this->assertSame([], $refs['nodes']);
	}

	public function testEmptyAndNonArrayContent(): void
	{
		$this->assertSame(['assets' => [], 'nodes' => []], new Scanner()->scan([]));
		$this->assertSame(['assets' => [], 'nodes' => []], new Scanner()->scan(null));
		$this->assertSame(['assets' => [], 'nodes' => []], new Scanner()->scan('html'));
	}

	private function doc(array $content): array
	{
		return ['type' => 'doc', 'content' => $content];
	}

	private function paragraph(array $content): array
	{
		return ['type' => 'paragraph', 'content' => $content];
	}

	private function text(string $text, array $marks = []): array
	{
		$node = ['type' => 'text', 'text' => $text];

		if ($marks !== []) {
			$node['marks'] = $marks;
		}

		return $node;
	}
}
