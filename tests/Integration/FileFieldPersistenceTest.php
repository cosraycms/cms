<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Integration;

use Celemas\Cms\Tests\IntegrationTestCase;

/**
 * Tests for File field persistence with various configurations.
 *
 * @internal
 *
 * @coversNothing
 */
final class FileFieldPersistenceTest extends IntegrationTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types', 'sample-nodes');
	}

	public function testSingleFileField(): void
	{
		$typeId = $this->createTestType('single-file-test');

		$fileContent = [
			'document' => [
				'type' => 'file',
				'files' => [
					['file' => 'document.pdf', 'title' => 'My Document'],
				],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'single-file-node',
			'type' => $typeId,
			'content' => $fileContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$this->assertCount(1, $content['document']['files']);
		$this->assertEquals('document.pdf', $content['document']['files'][0]['file']);
		$this->assertEquals('My Document', $content['document']['files'][0]['title']);
	}

	public function testMultipleFilesField(): void
	{
		$typeId = $this->createTestType('multiple-files-test');

		$fileContent = [
			'attachments' => [
				'type' => 'file',
				'files' => [
					['file' => 'file1.pdf', 'title' => 'First File'],
					['file' => 'file2.docx', 'title' => 'Second File'],
					['file' => 'file3.jpg', 'title' => 'Third File'],
				],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'multiple-files-node',
			'type' => $typeId,
			'content' => $fileContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$this->assertCount(3, $content['attachments']['files']);
		$this->assertEquals('file1.pdf', $content['attachments']['files'][0]['file']);
		$this->assertEquals('file2.docx', $content['attachments']['files'][1]['file']);
		$this->assertEquals('file3.jpg', $content['attachments']['files'][2]['file']);
	}

	public function testImageFieldWithMetadata(): void
	{
		$typeId = $this->createTestType('image-metadata-test');

		$imageContent = [
			'hero' => [
				'type' => 'image',
				'files' => [
					[
						'file' => 'hero.jpg',
						'title' => 'Hero Image',
						'alt' => 'A beautiful hero image',
					],
				],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'image-metadata-node',
			'type' => $typeId,
			'content' => $imageContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$image = $content['hero']['files'][0];
		$this->assertEquals('hero.jpg', $image['file']);
		$this->assertEquals('Hero Image', $image['title']);
		$this->assertEquals('A beautiful hero image', $image['alt']);
	}

	public function testImageFieldWithTranslatableAlt(): void
	{
		$typeId = $this->createTestType('image-translatable-test');

		$imageContent = [
			'gallery' => [
				'type' => 'image',
				'files' => [
					[
						'file' => 'photo.jpg',
						'alt' => [
							'de' => 'Deutsche Bildbeschreibung',
							'en' => 'English image description',
						],
					],
				],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'image-translatable-node',
			'type' => $typeId,
			'content' => $imageContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$alt = $content['gallery']['files'][0]['alt'];
		$this->assertEquals('Deutsche Bildbeschreibung', $alt['de']);
		$this->assertEquals('English image description', $alt['en']);
	}

	public function testFileFieldWithTranslatableTitle(): void
	{
		$typeId = $this->createTestType('file-translatable-test');

		$fileContent = [
			'download' => [
				'type' => 'file',
				'files' => [
					[
						'file' => 'manual.pdf',
						'title' => [
							'de' => 'Deutsches Handbuch',
							'en' => 'English Manual',
						],
					],
				],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'file-translatable-node',
			'type' => $typeId,
			'content' => $fileContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$title = $content['download']['files'][0]['title'];
		$this->assertEquals('Deutsches Handbuch', $title['de']);
		$this->assertEquals('English Manual', $title['en']);
	}

	public function testPictureFieldWithMultipleSources(): void
	{
		$typeId = $this->createTestType('picture-multiple-test');

		$pictureContent = [
			'hero' => [
				'type' => 'picture',
				'files' => [
					[
						'file' => 'hero-large.webp',
						'media' => '(min-width: 1200px)',
						'alt' => 'Hero large',
					],
					[
						'file' => 'hero-medium.webp',
						'media' => '(min-width: 768px)',
						'alt' => 'Hero medium',
					],
					[
						'file' => 'hero-small.webp',
						'alt' => 'Hero small',
					],
				],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'picture-multiple-node',
			'type' => $typeId,
			'content' => $pictureContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$files = $content['hero']['files'];
		$this->assertCount(3, $files);
		$this->assertEquals('(min-width: 1200px)', $files[0]['media']);
		$this->assertEquals('(min-width: 768px)', $files[1]['media']);
		$this->assertArrayNotHasKey('media', $files[2]); // Default has no media query
	}

	public function testEmptyFileField(): void
	{
		$typeId = $this->createTestType('empty-file-test');

		$fileContent = [
			'optional' => [
				'type' => 'file',
				'files' => [],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'empty-file-node',
			'type' => $typeId,
			'content' => $fileContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$this->assertIsArray($content['optional']['files']);
		$this->assertCount(0, $content['optional']['files']);
	}

	public function testVideoField(): void
	{
		$typeId = $this->createTestType('video-test');

		$videoContent = [
			'teaser' => [
				'type' => 'video',
				'files' => [
					[
						'file' => 'teaser.mp4',
						'title' => 'Product Teaser',
					],
				],
			],
		];

		$nodeId = $this->createTestNode([
			'uid' => 'video-node',
			'type' => $typeId,
			'content' => $videoContent,
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$this->assertEquals('teaser.mp4', $content['teaser']['files'][0]['file']);
		$this->assertEquals('Product Teaser', $content['teaser']['files'][0]['title']);
	}
}
