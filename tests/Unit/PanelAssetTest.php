<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Request;
use Cosray\Controller\Panel\Assets;
use Cosray\Panel\Client;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PanelAssetTest extends TestCase
{
	public function testClientReturnsNotFoundOutsideTheServedDirectories(): void
	{
		$panel = new Assets($this->config(), $this->container(), $this->request());

		foreach ([
			'../composer.json',
			'styles/../../composer.json',
			'prettier.config.js',
			'views/layer/document.php',
		] as $slug) {
			try {
				$panel->asset($this->request(), $this->factory(), 'dev', $slug);
				$this->fail("{$slug} was served");
			} catch (HttpNotFound) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testClientReturnsNotModifiedWhenEtagMatches(): void
	{
		$panel = new Assets($this->config(), $this->container(), $this->request());
		$file = self::root() . '/panel/styles/panel.css';
		$etag = md5_file($file);
		$this->assertNotFalse($etag);
		$request = new Request($this->psrRequest()->withHeader('If-None-Match', '"' . $etag . '"'));

		$response = $panel->asset($request, $this->factory(), 'dev', 'styles/panel.css');

		$this->assertSame(304, $response->getStatusCode());
		$this->assertSame(['no-cache'], $response->getHeader('Cache-Control'));
		$this->assertSame(['"' . $etag . '"'], $response->getHeader('ETag'));
		$this->assertSame([], $response->getHeader('Content-Type'));
	}

	public function testClientReturnsPackageFileRevalidatedForAnotherRevision(): void
	{
		$panel = new Assets($this->config(), $this->container(), $this->request());
		$file = self::root() . '/panel/styles/panel.css';
		$etag = md5_file($file);
		$this->assertNotFalse($etag);

		$response = $panel->asset($this->request(), $this->factory(), 'dev', 'styles/panel.css');

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame(['text/css'], $response->getHeader('Content-Type'));
		$this->assertSame(['no-cache'], $response->getHeader('Cache-Control'));
		$this->assertSame(['"' . $etag . '"'], $response->getHeader('ETag'));
		$this->assertNotSame([], $response->getHeader('Last-Modified'));
		$this->assertSame(file_get_contents($file), (string) $response->getBody());
	}

	public function testClientServesVendoredModulesAsJavascript(): void
	{
		$panel = new Assets($this->config(), $this->container(), $this->request());

		$response = $panel->asset(
			$this->request(),
			$this->factory(),
			'dev',
			'modules/prosemirror-view/dist/index.js',
		);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame(['text/javascript'], $response->getHeader('Content-Type'));
	}

	public function testClientServesTheIconSprite(): void
	{
		$panel = new Assets($this->config(), $this->container(), $this->request());

		$response = $panel->asset($this->request(), $this->factory(), 'dev', 'icons.svg');
		$sprite = (string) $response->getBody();

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame(['image/svg+xml'], $response->getHeader('Content-Type'));
		$this->assertSame(['"' . md5($sprite) . '"'], $response->getHeader('ETag'));
		$this->assertStringContainsString('<symbol id="plus" ', $sprite);
	}

	public function testPanelContextLoadsThePackageFiles(): void
	{
		$client = new Client($this->config());

		$context = $this->panel()->data();

		$this->assertContains($client->url('styles/panel.css'), $context['stylesheets']);
		// htmx loads as a classic script: plugins rely on its global.
		$this->assertSame([$client->url('modules/htmx.org/dist/htmx.js')], $context['scripts']);
		$this->assertSame([$client->url('src/panel.js')], $context['moduleScripts']);
		$this->assertContains($client->url('src/behaviors/blocks.js'), $context['modulePreloads']);
		$this->assertContains($client->url('modules/@celema/verba/dist/index.js'), $context['modulePreloads']);
	}

	public function testTheDevServerServesOnlyTheStylesheet(): void
	{
		$_SERVER['COSRAY_PANEL_DEV'] = '1';
		$_SERVER['COSRAY_PANEL_DEV_ORIGIN'] = 'http://localhost:2001/';
		$client = new Client($this->config());

		try {
			$context = $this->panel()->data();

			$this->assertNotContains($client->url('styles/panel.css'), $context['stylesheets']);
			$this->assertSame(
				['http://localhost:2001/@vite/client', 'http://localhost:2001/dev.js', $client->url('src/panel.js')],
				$context['moduleScripts'],
			);
			// Scripts, import map and icons stay as in production.
			$this->assertSame([$client->url('modules/htmx.org/dist/htmx.js')], $context['scripts']);
			$this->assertContains($client->url('src/behaviors/blocks.js'), $context['modulePreloads']);
			$this->assertSame($client->url(), $context['assetsBase']);
		} finally {
			unset($_SERVER['COSRAY_PANEL_DEV'], $_SERVER['COSRAY_PANEL_DEV_ORIGIN']);
		}
	}

	private function panel(array $config = []): \Cosray\Controller\Panel\Panel
	{
		return new class(
			$this->config($config),
			$this->container(),
			$this->request(),
		) extends \Cosray\Controller\Panel\Panel {
			public function data(): array
			{
				return $this->context();
			}

			protected function collections(): array
			{
				return [];
			}
		};
	}
}
