<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Plugin\TestPlugin;

final class PanelPluginPageTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$bootstrap = parent::createBootstrap($config);
		$bootstrap->plugin(TestPlugin::class);

		return $bootstrap;
	}

	public function testPluginPanelPageRendersInsideChrome(): void
	{
		$this->authenticateAs('editor');
		$response = $this->makeRequest('GET', '/cp/test-plugin');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('<!DOCTYPE html>', $html);
		$this->assertStringContainsString('id="main" class="page test-plugin-page"', $html);
		$this->assertStringContainsString('<h1>Test Plugin Page</h1>', $html);
		// The plugin's nav link renders in the sidebar with the section label.
		$this->assertStringContainsString('Test Plugin', $html);
		$this->assertStringContainsString('href="/cp/test-plugin"', $html);
		$this->assertStringContainsString('aria-current="page"', $html);
		// Injected plugin stylesheet is linked from the chrome.
		$this->assertStringContainsString('/cp/vendor/test-plugin/theme.css', $html);
	}

	public function testPluginPanelPageRequiresAuthentication(): void
	{
		$response = $this->makeRequest('GET', '/cp/test-plugin');

		$this->assertSame(303, $response->getStatusCode());
	}

	public function testPluginVendorAssetsAreServed(): void
	{
		$this->authenticateAs('editor');

		$js = $this->makeRequest('GET', '/cp/vendor/test-plugin/controls.js');
		$this->assertResponseOk($js);
		$this->assertStringContainsString('test-money', (string) $js->getBody());

		$css = $this->makeRequest('GET', '/cp/vendor/test-plugin/theme.css');
		$this->assertResponseOk($css);

		$missing = $this->makeRequest('GET', '/cp/vendor/unknown/controls.js');
		$this->assertSame(404, $missing->getStatusCode());

		$escape = $this->makeRequest('GET', '/cp/vendor/test-plugin/../TestPlugin.php');
		$this->assertSame(404, $escape->getStatusCode());
	}
}
