<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Tests\IntegrationTestCase;

/**
 * Tests for multi-locale content fallback and retrieval scenarios.
 *
 * @internal
 *
 * @coversNothing
 */
final class MultiLocaleScenariosTest extends IntegrationTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types', 'sample-nodes');
	}

	public function testFallbackToDefaultLocale(): void
	{
		$typeId = $this->createTestType('fallback-test');

		$nodeId = $this->createTestNode([
			'uid' => 'fallback-node',
			'type' => $typeId,
			'content' => [
				'title' => [
					'type' => 'text',
					'value' => [
						'en' => 'English Title',
						'de' => null, // Missing German translation
					],
				],
			],
		]);

		// Create path for German locale
		$this->createTestPath($nodeId, '/fallback-test', 'de');

		// Query the node content
		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);

		// German locale should fallback to English
		$this->assertNull($content['title']['value']['de']);
		$this->assertEquals('English Title', $content['title']['value']['en']);
	}

	public function testLocaleSpecificContent(): void
	{
		$typeId = $this->createTestType('locale-specific-test');

		$nodeId = $this->createTestNode([
			'uid' => 'locale-specific-node',
			'type' => $typeId,
			'content' => [
				'title' => [
					'type' => 'text',
					'value' => [
						'en' => 'English Title',
						'de' => 'Deutscher Titel',
						'fr' => 'Titre Français',
					],
				],
				'body' => [
					'type' => 'richtext',
					'value' => [
						'en' => '<p>English content</p>',
						'de' => '<p>Deutscher Inhalt</p>',
						'fr' => '<p>Contenu Français</p>',
					],
				],
			],
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);

		// Verify each locale has distinct content
		$this->assertEquals('English Title', $content['title']['value']['en']);
		$this->assertEquals('Deutscher Titel', $content['title']['value']['de']);
		$this->assertEquals('Titre Français', $content['title']['value']['fr']);

		$this->assertEquals('<p>English content</p>', $content['body']['value']['en']);
		$this->assertEquals('<p>Deutscher Inhalt</p>', $content['body']['value']['de']);
		$this->assertEquals('<p>Contenu Français</p>', $content['body']['value']['fr']);
	}

	public function testTranslatableFileFieldsPerLocale(): void
	{
		$typeId = $this->createTestType('locale-file-test');

		$nodeId = $this->createTestNode([
			'uid' => 'locale-file-node',
			'type' => $typeId,
			'content' => [
				'document' => [
					'type' => 'file',
					'files' => [
						'en' => [
							['file' => 'manual-en.pdf', 'title' => 'English Manual'],
						],
						'de' => [
							['file' => 'handbuch-de.pdf', 'title' => 'Deutsches Handbuch'],
						],
					],
				],
			],
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);

		// Each locale has its own file
		$this->assertEquals('manual-en.pdf', $content['document']['files']['en'][0]['file']);
		$this->assertEquals('handbuch-de.pdf', $content['document']['files']['de'][0]['file']);
	}

	public function testTranslatableImageAltPerLocale(): void
	{
		$typeId = $this->createTestType('locale-image-test');

		$nodeId = $this->createTestNode([
			'uid' => 'locale-image-node',
			'type' => $typeId,
			'content' => [
				'hero' => [
					'type' => 'image',
					'files' => [
						[
							'file' => 'hero.jpg',
							'alt' => [
								'en' => 'English alt text',
								'de' => 'Deutscher Alt-Text',
								'es' => 'Texto alternativo español',
							],
						],
					],
				],
			],
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$alt = $content['hero']['files'][0]['alt'];

		$this->assertEquals('English alt text', $alt['en']);
		$this->assertEquals('Deutscher Alt-Text', $alt['de']);
		$this->assertEquals('Texto alternativo español', $alt['es']);
	}

	public function testPathsForMultipleLocales(): void
	{
		$typeId = $this->createTestType('multi-locale-paths-test');

		$nodeId = $this->createTestNode([
			'uid' => 'multi-locale-node',
			'type' => $typeId,
		]);

		// Create paths for different locales
		$this->createTestPath($nodeId, '/about-us', 'en');
		$this->createTestPath($nodeId, '/ueber-uns', 'de');
		$this->createTestPath($nodeId, '/a-propos', 'fr');

		$paths = $this->db()->execute(
			'SELECT path, locale FROM cms.url_paths WHERE node = :node',
			['node' => $nodeId],
		)->all();

		$this->assertCount(3, $paths);

		// Build a map of locale -> path for easier assertions
		$localePaths = [];
		foreach ($paths as $path) {
			$localePaths[$path['locale']] = $path['path'];
		}

		$this->assertEquals('/about-us', $localePaths['en']);
		$this->assertEquals('/ueber-uns', $localePaths['de']);
		$this->assertEquals('/a-propos', $localePaths['fr']);
	}

	public function testPartialTranslationFallbackChain(): void
	{
		$typeId = $this->createTestType('partial-fallback-test');

		// Create content with only some locales translated
		$nodeId = $this->createTestNode([
			'uid' => 'partial-fallback-node',
			'type' => $typeId,
			'content' => [
				'title' => [
					'type' => 'text',
					'value' => [
						'en' => 'English Title', // Default locale
						'de' => 'Deutscher Titel', // Translated
						// 'fr' is missing - should fallback to 'en'
					],
				],
			],
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);

		// Verify what's there
		$this->assertEquals('English Title', $content['title']['value']['en']);
		$this->assertEquals('Deutscher Titel', $content['title']['value']['de']);
		// 'fr' key should not exist or be null
		$this->assertTrue(
			!isset($content['title']['value']['fr']) || $content['title']['value']['fr'] === null,
		);
	}

	public function testBlocksWithTranslatableItems(): void
	{
		$typeId = $this->createTestType('locale-blocks-test');

		$nodeId = $this->createTestNode([
			'uid' => 'locale-blocks-node',
			'type' => $typeId,
			'content' => [
				'blocks' => [
					'type' => 'blocks',
					'items' => [
						[
							'type' => 'text',
							'rowspan' => 1,
							'colspan' => 6,
							'value' => [
								'en' => 'English blocks text',
								'de' => 'Deutscher Blocks-Text',
							],
						],
						[
							'type' => 'richtext',
							'rowspan' => 1,
							'colspan' => 6,
							'value' => [
								'en' => '<p>English HTML</p>',
								'de' => '<p>Deutsches HTML</p>',
							],
						],
					],
				],
			],
		]);

		$node = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE node = :id',
			['id' => $nodeId],
		)->one();

		$content = json_decode($node['content'], true);
		$items = $content['blocks']['items'];

		$this->assertEquals('English blocks text', $items[0]['value']['en']);
		$this->assertEquals('Deutscher Blocks-Text', $items[0]['value']['de']);
		$this->assertEquals('<p>English HTML</p>', $items[1]['value']['en']);
		$this->assertEquals('<p>Deutsches HTML</p>', $items[1]['value']['de']);
	}
}
