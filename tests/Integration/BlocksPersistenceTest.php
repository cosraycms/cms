<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Tests\IntegrationTestCase;

/**
 * Tests for Blocks field persistence with various content types.
 *
 * @internal
 *
 * @coversNothing
 */
final class BlocksPersistenceTest extends IntegrationTestCase
{
	private function items(array $content, string $field): array
	{
		return $content[$field]['value'][\Cosray\Field\Field::NEUTRAL_LOCALE] ?? [];
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types', 'sample-nodes');
	}

	public function testBlocksWithTextAndHtmlItems(): void
	{
		$typeId = $this->createTestType('blocks-text-html-test');

		$blocksContent = [
			'blocks' => [
				'type' => 'blocks',
				'items' => [
					[
						'type' => 'text',
						'rowspan' => 1,
						'colspan' => 6,
						'colstart' => 1,
						'value' => 'First text block',
					],
					[
						'type' => 'richtext',
						'rowspan' => 1,
						'colspan' => 6,
						'colstart' => 7,
						'value' => '<p>HTML paragraph</p>',
					],
				],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'blocks-text-html-node',
			'type' => $typeId,
			'content' => $blocksContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$items = $this->items($content, 'blocks');
		$this->assertCount(2, $items);
		$this->assertEquals('text', $items[0]['type']);
		$this->assertEquals('richtext', $items[1]['type']);
	}

	public function testBlocksWithImageItems(): void
	{
		$typeId = $this->createTestType('blocks-image-test');

		$blocksContent = [
			'gallery' => [
				'type' => 'blocks',
				'items' => [
					[
						'type' => 'image',
						'rowspan' => 2,
						'colspan' => 4,
						'colstart' => 1,
						'files' => [
							['file' => 'photo1.jpg', 'title' => 'Photo 1', 'alt' => 'First photo'],
						],
					],
					[
						'type' => 'image',
						'rowspan' => 2,
						'colspan' => 4,
						'colstart' => 5,
						'files' => [
							['file' => 'photo2.jpg', 'title' => 'Photo 2', 'alt' => 'Second photo'],
						],
					],
				],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'blocks-image-node',
			'type' => $typeId,
			'content' => $blocksContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$items = $this->items($content, 'gallery');
		$this->assertCount(2, $items);
		$this->assertEquals('image', $items[0]['type']);
		$this->assertEquals('photo1.jpg', $items[0]['value'][0]['file']);
	}

	public function testBlocksWithYoutubeItem(): void
	{
		$typeId = $this->createTestType('blocks-youtube-test');

		$blocksContent = [
			'content' => [
				'type' => 'blocks',
				'items' => [
					[
						'type' => 'youtube',
						'rowspan' => 1,
						'colspan' => 12,
						'colstart' => 1,
						'id' => 'dQw4w9WgXcQ',
						'aspectRatioX' => 16,
						'aspectRatioY' => 9,
					],
				],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'blocks-youtube-node',
			'type' => $typeId,
			'content' => $blocksContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$items = $this->items($content, 'content');
		$this->assertEquals('youtube', $items[0]['type']);
		$this->assertEquals('dQw4w9WgXcQ', $items[0]['value'][\Cosray\Field\Field::NEUTRAL_LOCALE]);
		$this->assertEquals(16, $items[0]['meta']['aspectRatioX'][\Cosray\Field\Field::NEUTRAL_LOCALE]);
	}

	public function testBlocksWithMixedItemTypes(): void
	{
		$typeId = $this->createTestType('blocks-mixed-test');

		$blocksContent = [
			'mixed' => [
				'type' => 'blocks',
				'items' => [
					['type' => 'text', 'rowspan' => 1, 'colspan' => 4, 'value' => 'Text'],
					['type' => 'richtext', 'rowspan' => 1, 'colspan' => 4, 'value' => '<p>HTML</p>'],
					['type' => 'image', 'rowspan' => 1, 'colspan' => 4, 'files' => [['file' => 'img.jpg']]],
					[
						'type' => 'youtube',
						'rowspan' => 1,
						'colspan' => 12,
						'id' => 'abc123',
						'aspectRatioX' => 16,
						'aspectRatioY' => 9,
					],
				],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'blocks-mixed-node',
			'type' => $typeId,
			'content' => $blocksContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$items = $this->items($content, 'mixed');

		$this->assertCount(4, $items);
		$this->assertEquals('text', $items[0]['type']);
		$this->assertEquals('richtext', $items[1]['type']);
		$this->assertEquals('image', $items[2]['type']);
		$this->assertEquals('youtube', $items[3]['type']);
	}

	public function testBlocksWithTranslatableContent(): void
	{
		$typeId = $this->createTestType('blocks-translatable-test');

		$blocksContent = [
			'blocks' => [
				'type' => 'blocks',
				'items' => [
					[
						'type' => 'text',
						'rowspan' => 1,
						'colspan' => 6,
						'value' => [
							'de' => 'Deutscher Text',
							'en' => 'English text',
						],
					],
					[
						'type' => 'richtext',
						'rowspan' => 1,
						'colspan' => 6,
						'value' => [
							'de' => '<p>Deutscher HTML</p>',
							'en' => '<p>English HTML</p>',
						],
					],
				],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'blocks-translatable-node',
			'type' => $typeId,
			'content' => $blocksContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$items = $this->items($content, 'blocks');
		$this->assertEquals('Deutscher Text', $items[0]['value']['de']);
		$this->assertEquals('English text', $items[0]['value']['en']);
	}

	public function testEmptyBlocksStructure(): void
	{
		$typeId = $this->createTestType('blocks-empty-test');

		$blocksContent = [
			'emptyblocks' => [
				'type' => 'blocks',
				'items' => [],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'blocks-empty-node',
			'type' => $typeId,
			'content' => $blocksContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$this->assertIsArray($this->items($content, 'emptyblocks'));
		$this->assertCount(0, $this->items($content, 'emptyblocks'));
	}

	public function testBlocksComplexLayout(): void
	{
		$typeId = $this->createTestType('blocks-layout-test');

		// Create a 12-column layout with various spans
		$blocksContent = [
			'layout' => [
				'type' => 'blocks',
				'items' => [
					[
						'type' => 'text',
						'rowspan' => 1,
						'colspan' => 12,
						'colstart' => 1,
						'value' => 'Full width header',
					],
					[
						'type' => 'richtext',
						'rowspan' => 1,
						'colspan' => 6,
						'colstart' => 1,
						'value' => '<p>Left column</p>',
					],
					[
						'type' => 'richtext',
						'rowspan' => 1,
						'colspan' => 6,
						'colstart' => 7,
						'value' => '<p>Right column</p>',
					],
					[
						'type' => 'image',
						'rowspan' => 1,
						'colspan' => 4,
						'colstart' => 1,
						'files' => [['file' => '1.jpg']],
					],
					[
						'type' => 'image',
						'rowspan' => 1,
						'colspan' => 4,
						'colstart' => 5,
						'files' => [['file' => '2.jpg']],
					],
					[
						'type' => 'image',
						'rowspan' => 1,
						'colspan' => 4,
						'colstart' => 9,
						'files' => [['file' => '3.jpg']],
					],
				],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'blocks-layout-node',
			'type' => $typeId,
			'content' => $blocksContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$items = $this->items($content, 'layout');

		// Verify layout structure
		$this->assertEquals(12, $items[0]['colspan']);
		$this->assertEquals(1, $items[0]['colstart']);
		$this->assertEquals(6, $items[1]['colspan']);
		$this->assertEquals(1, $items[1]['colstart']);
		$this->assertEquals(6, $items[2]['colspan']);
		$this->assertEquals(7, $items[2]['colstart']);
	}
}
