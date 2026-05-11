<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Integration;

use Celemas\Cms\Tests\IntegrationTestCase;

/**
 * Tests for Grid field persistence with various content types.
 *
 * @internal
 *
 * @coversNothing
 */
final class GridPersistenceTest extends IntegrationTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types', 'sample-nodes');
	}

	public function testGridWithTextAndHtmlItems(): void
	{
		$typeId = $this->createTestType('grid-text-html-test');

		$gridContent = [
			'grid' => [
				'type' => 'grid',
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
			'uid' => 'grid-text-html-node',
			'type' => $typeId,
			'content' => $gridContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$this->assertCount(2, $content['grid']['items']);
		$this->assertEquals('text', $content['grid']['items'][0]['type']);
		$this->assertEquals('richtext', $content['grid']['items'][1]['type']);
	}

	public function testGridWithImageItems(): void
	{
		$typeId = $this->createTestType('grid-image-test');

		$gridContent = [
			'gallery' => [
				'type' => 'grid',
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
			'uid' => 'grid-image-node',
			'type' => $typeId,
			'content' => $gridContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$this->assertCount(2, $content['gallery']['items']);
		$this->assertEquals('image', $content['gallery']['items'][0]['type']);
		$this->assertEquals('photo1.jpg', $content['gallery']['items'][0]['files'][0]['file']);
	}

	public function testGridWithYoutubeItem(): void
	{
		$typeId = $this->createTestType('grid-youtube-test');

		$gridContent = [
			'content' => [
				'type' => 'grid',
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
			'uid' => 'grid-youtube-node',
			'type' => $typeId,
			'content' => $gridContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$this->assertEquals('youtube', $content['content']['items'][0]['type']);
		$this->assertEquals('dQw4w9WgXcQ', $content['content']['items'][0]['id']);
		$this->assertEquals(16, $content['content']['items'][0]['aspectRatioX']);
	}

	public function testGridWithMixedItemTypes(): void
	{
		$typeId = $this->createTestType('grid-mixed-test');

		$gridContent = [
			'mixed' => [
				'type' => 'grid',
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
			'uid' => 'grid-mixed-node',
			'type' => $typeId,
			'content' => $gridContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$items = $content['mixed']['items'];

		$this->assertCount(4, $items);
		$this->assertEquals('text', $items[0]['type']);
		$this->assertEquals('richtext', $items[1]['type']);
		$this->assertEquals('image', $items[2]['type']);
		$this->assertEquals('youtube', $items[3]['type']);
	}

	public function testGridWithTranslatableContent(): void
	{
		$typeId = $this->createTestType('grid-translatable-test');

		$gridContent = [
			'grid' => [
				'type' => 'grid',
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
			'uid' => 'grid-translatable-node',
			'type' => $typeId,
			'content' => $gridContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$this->assertEquals('Deutscher Text', $content['grid']['items'][0]['value']['de']);
		$this->assertEquals('English text', $content['grid']['items'][0]['value']['en']);
	}

	public function testEmptyGridStructure(): void
	{
		$typeId = $this->createTestType('grid-empty-test');

		$gridContent = [
			'emptygrid' => [
				'type' => 'grid',
				'items' => [],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'grid-empty-node',
			'type' => $typeId,
			'content' => $gridContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$this->assertIsArray($content['emptygrid']['items']);
		$this->assertCount(0, $content['emptygrid']['items']);
	}

	public function testGridComplexLayout(): void
	{
		$typeId = $this->createTestType('grid-layout-test');

		// Create a 12-column layout with various spans
		$gridContent = [
			'layout' => [
				'type' => 'grid',
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
			'uid' => 'grid-layout-node',
			'type' => $typeId,
			'content' => $gridContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$items = $content['layout']['items'];

		// Verify layout structure
		$this->assertEquals(12, $items[0]['colspan']);
		$this->assertEquals(1, $items[0]['colstart']);
		$this->assertEquals(6, $items[1]['colspan']);
		$this->assertEquals(1, $items[1]['colstart']);
		$this->assertEquals(6, $items[2]['colspan']);
		$this->assertEquals(7, $items[2]['colstart']);
	}
}
