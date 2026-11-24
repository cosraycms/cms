<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Console\Runtime;
use Cosray\Node\Write;
use Cosray\Node\Writer;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Node\TestDocument;
use Cosray\Tests\Fixtures\Plugin\TestPlugin;
use Cosray\Tests\Fixtures\Plugin\TestWriteLog;

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
		$response = $this->makeRequest('GET', '/panel/test-plugin');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('<!DOCTYPE html>', $html);
		$this->assertStringContainsString('class="cms-page test-plugin-page"', $html);
		$this->assertStringContainsString('<h1>Test Plugin Page</h1>', $html);
		// The plugin's nav link renders in the sidebar with the section label.
		$this->assertStringContainsString('Test Plugin', $html);
		$this->assertStringContainsString('href="/panel/test-plugin"', $html);
		$this->assertStringContainsString('aria-current="page"', $html);
		// A relative stylesheet is served under the panel revision, a URL as it is.
		$this->assertMatchesRegularExpression('~href="/panel/assets/[0-9a-z]+/plugins/test-plugin/theme\.css"~', $html);
		$this->assertStringContainsString('href="/panel/vendor/test-plugin/theme.css"', $html);
	}

	public function testAPluginListenerHearsNodeWrites(): void
	{
		TestWriteLog::$writes = [];
		// Built by the container, as a job or a command gets it.
		$writer = new Runtime($this->app->container())->get(Writer::class);

		$writer->create($writer->prepare(TestDocument::class, ['title' => 'Heard'])->uid('plugin-heard'));

		$this->assertSame([['plugin-heard', 'test-document', ['created']]], array_map(
			static fn(Write $write): array => [$write->uid, $write->type, $write->actions],
			TestWriteLog::$writes,
		));
	}

	public function testAPluginEntryJoinsTheSystemRailOfUsersWithItsPermission(): void
	{
		$this->authenticateAs('admin');
		$html = $this->getHtmlResponse($this->makeRequest('GET', '/panel/test-plugin/stats'));

		$this->assertHtmlNodeExists('//a[@id="system-link" and @href="/panel/users" and @aria-current="page"]', $html);
		$this->assertHtmlNodeExists('//*[@id="system-nav"]//li[1]/a[@href="/panel/users"]', $html);
		$this->assertHtmlNodeExists(
			'//*[@id="system-nav"]//li[2]/a[@href="/panel/test-plugin/stats" and @aria-current="page" and normalize-space(.)="Test Stats"]',
			$html,
		);
		$this->assertHtmlNodeExists('//*[@id="system-nav"]//li[3]/a[@href="/panel/profile"]', $html);
	}

	public function testTheLongestMatchingLinkOfARailIsCurrentBelowIt(): void
	{
		$this->authenticateAs('admin');
		$html = $this->getHtmlResponse($this->makeRequest('GET', '/panel/test-plugin/stats/today'));

		$this->assertHtmlNodeExists(
			'//*[@id="system-nav"]//a[@href="/panel/test-plugin/stats" and @aria-current="page"]',
			$html,
		);
		$this->assertHtmlNodeMissing('//*[@id="system-nav"]//a[@href="/panel/users" and @aria-current]', $html);

		$html = $this->getHtmlResponse($this->makeRequest('GET', '/panel/test-plugin/sub'));
		$this->assertHtmlNodeExists('//a[@href="/panel/test-plugin/sub" and @aria-current="page"]', $html);
		$this->assertHtmlNodeMissing('//a[@href="/panel/test-plugin" and @aria-current]', $html);
	}

	public function testAPluginEntryIsHiddenAndItsPageRefusedWithoutItsPermission(): void
	{
		$this->authenticateAs('editor');

		$this->assertHtmlNodeMissing(
			'//*[@id="system-nav"]//a[@href="/panel/test-plugin/stats"]',
			$this->getHtmlResponse($this->makeRequest('GET', '/panel/profile')),
		);
		$this->assertResponseStatus(403, $this->makeRequest('GET', '/panel/test-plugin/stats'));
	}

	public function testAPluginPostRouteRunsInsideThePanelGroup(): void
	{
		$this->authenticateAs('editor');
		$response = $this->makeRequest('POST', '/panel/test-plugin', ['form' => ['name' => 'Ada Lovelace']]);

		$this->assertSame(303, $response->getStatusCode());
		$this->assertSame('/panel/test-plugin?saved=Ada%20Lovelace', $response->getHeaderLine('Location'));
		$this->assertSame('/panel/test-plugin', $this->app->router()->url('cms.panel.test-plugin.save'));
	}

	public function testAPluginPostRouteNeedsTheCsrfTokenOfASession(): void
	{
		$response = $this->makeRequest('POST', '/panel/test-plugin', ['form' => ['name' => 'x'], 'csrf' => false]);

		$this->assertSame(303, $response->getStatusCode());
		$this->assertStringStartsWith('/panel/expired', $response->getHeaderLine('Location'));
	}

	public function testThePluginUrlHelperBuildsUrlsBelowThePluginPages(): void
	{
		$this->authenticateAs('editor');
		$html = $this->getHtmlResponse($this->makeRequest('GET', '/panel/test-plugin'));

		$this->assertHtmlNodeExists('//a[@class="stats" and @href="/panel/test-plugin/stats?tab=all"]', $html);
	}

	public function testAPluginCatalogTranslatesItsPagesAndReachesTheScripts(): void
	{
		$this->authenticateAs('editor');
		$html = $this->getHtmlResponse($this->makeRequest('GET', '/panel/test-plugin', [
			'headers' => ['Accept-Language' => 'de'],
		]));

		$this->assertHtmlNodeExists('//p[@class="greeting" and .="Hallo vom Plugin"]', $html);
		// A language the plugin has no entry for falls back like Cosray's own catalogs.
		$this->assertHtmlNodeExists('//p[@class="english" and .="Only in English"]', $html);
		$this->assertStringContainsString('"domain":"test-plugin"', $html);
		$this->assertStringContainsString('Hallo vom Plugin', $html);

		$html = $this->getHtmlResponse($this->makeRequest('GET', '/panel/test-plugin', [
			'headers' => ['Accept-Language' => 'fr'],
		]));
		$this->assertHtmlNodeExists('//html[@lang="fr"]', $html);
		$this->assertHtmlNodeExists('//p[@class="greeting" and .="Hello from the plugin"]', $html);
	}

	public function testPluginPanelPageRequiresAuthentication(): void
	{
		$response = $this->makeRequest('GET', '/panel/test-plugin');

		$this->assertSame(303, $response->getStatusCode());
	}

	public function testPluginAssetsAreServedUnderTheRevision(): void
	{
		$this->authenticateAs('editor');
		$html = $this->getHtmlResponse($this->makeRequest('GET', '/panel/test-plugin'));
		preg_match('~href="(/panel/assets/[0-9a-z]+/plugins/test-plugin/theme\.css)"~', $html, $match);

		$css = $this->makeRequest('GET', $match[1] ?? '');
		$this->assertResponseOk($css);
		$this->assertStringContainsString('immutable', $css->getHeaderLine('Cache-Control'));
		$this->assertSame(
			404,
			$this->makeRequest('GET', '/panel/assets/dev/plugins/test-plugin/../TestPlugin.php')->getStatusCode(),
		);
	}

	public function testPluginVendorAssetsAreServed(): void
	{
		$this->authenticateAs('editor');

		$js = $this->makeRequest('GET', '/panel/vendor/test-plugin/controls.js');
		$this->assertResponseOk($js);
		$this->assertStringContainsString('test-money', (string) $js->getBody());

		$css = $this->makeRequest('GET', '/panel/vendor/test-plugin/theme.css');
		$this->assertResponseOk($css);

		$missing = $this->makeRequest('GET', '/panel/vendor/unknown/controls.js');
		$this->assertSame(404, $missing->getStatusCode());

		$escape = $this->makeRequest('GET', '/panel/vendor/test-plugin/../TestPlugin.php');
		$this->assertSame(404, $escape->getStatusCode());
	}
}
