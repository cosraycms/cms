<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Celema\Core\Plugin as CorePlugin;
use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Locales;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;

final class PanelEditorLocaleControlTest extends End2EndTestCase
{
	protected function createLocales(): CorePlugin
	{
		$locales = new Locales();
		$locales->add('en', title: 'English', pgDict: 'english');
		$locales->add('de', title: 'Deutsch', fallback: 'en', pgDict: 'german');
		$locales->add('es', title: 'Español', fallback: 'en', pgDict: 'simple');
		$locales->add('fr', title: 'Français', fallback: 'en', pgDict: 'simple');

		return $locales;
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$plugin = parent::createBootstrap($config);
		$plugin->section('Content')->collection(TestArticlesCollection::class);

		return $plugin;
	}

	public function testFourLocalesUseASelect(): void
	{
		$this->authenticateAs('editor');
		$type = $this->createTestType('test-article');
		$this->createTestNode([
			'uid' => 'panel-editor-locales',
			'type' => $type,
			'published' => true,
			'content' => [
				'title' => [
					'type' => 'text',
					'value' => ['en' => 'Panel editor locales'],
				],
			],
		]);

		$response = $this->makeRequest(
			'GET',
			'/cp/collection/test-articles/panel-editor-locales',
		);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertHtmlNodeExists(
			'//select[@data-content-locale-control][@data-content-locale-select]'
				. '/option[@value="fr"]',
			$html,
		);
		$this->assertStringNotContainsString('data-content-locale-option', $html);
	}
}
