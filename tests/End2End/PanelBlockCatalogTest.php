<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Block;
use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Field\Blocks;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Block\CatalogBlock;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;
use Cosray\Tests\Fixtures\Node\TestCatalogDocument;

final class PanelBlockCatalogTest extends End2EndTestCase
{
	protected function createBootstrap(Config $config): Bootstrap
	{
		$bootstrap = parent::createBootstrap($config);
		$bootstrap->section('Content')->collection(TestArticlesCollection::class);
		$bootstrap->node(TestCatalogDocument::class);

		return $bootstrap;
	}

	public function testCatalogsOfferOnlyTheirFieldsAllowedTypesAndKeepAllTemplates(): void
	{
		$this->authenticateAs('editor');
		$this->createTestNode([
			'uid' => 'catalog-editor',
			'type' => $this->createTestType('test-catalog-document'),
			'content' => json_encode([
				'story' => [
					'type' => Blocks::class,
					'value' => [
						'zxx' => [[
							'uid' => 'existing',
							'type' => Block\Text::class,
							'fields' => ['text' => ['value' => ['en' => 'Original', 'de' => 'Ursprünglich']]],
						]],
					],
				],
			]),
		]);
		$response = $this->makeRequest('GET', '/cp/collection/test-articles/catalog-editor');
		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$story = '//*[@data-name="content[story][value][zxx]"]';
		$catalog = $story . '/template[@data-block-catalog]';
		$this->assertHtmlNodeCount(1, $catalog, $html);
		$this->assertHtmlNodeCount(3, $catalog . '//*[@data-block-choice]', $html);
		foreach ([Block\Text::class, CatalogBlock::class, Block\Iframe::class] as $type) {
			$this->assertHtmlNodeCount(1, $catalog . '//*[@data-block-choice="' . $type . '"]', $html);
			$this->assertHtmlNodeCount(1, $story . '/template[@data-repeater-template="' . $type . '"]', $html);
		}
		$footer = $story . '/*[@data-repeater-footer]';
		$this->assertHtmlNodeCount(1, $footer . '//*[@data-repeater-add]', $html);
		$this->assertHtmlNodeExists($footer . '//*[@data-repeater-add="' . CatalogBlock::class . '"]', $html);
		$this->assertHtmlNodeCount(1, $footer . '//*[@data-block-catalog-open]', $html);
		foreach (['en', 'de'] as $locale) {
			$list = '//*[@data-name="content[translated][value][' . $locale . ']"]';
			$this->assertHtmlNodeCount(8, $list . '/template[@data-block-catalog]//*[@data-block-choice]', $html);
			$this->assertHtmlNodeCount(6, $list . '/*[@data-repeater-footer]//*[@data-repeater-add]', $html);
		}
		$this->assertHtmlNodeExists(
			'//textarea[@name="content[story][value][zxx][0][fields][text][value][en]"][text()="Original"]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//textarea[@name="content[story][value][zxx][0][fields][text][value][de]"][text()="Ursprünglich"]',
			$html,
		);
	}
}
