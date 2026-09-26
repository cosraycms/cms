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

	public function testStaticAssetReturnsFileFromPanelAssetsDirectory(): void
	{
		$static = $this->createPanelAssets(['panel.js' => 'console.log("panel");']);
		$panel = new Assets(
			$this->config(['panel.assets_dir' => $static]),
			$this->container(),
			$this->request(),
		);

		try {
			$response = $panel->staticAsset($this->request(), $this->factory(), 'panel.js');

			$this->assertSame(200, $response->getStatusCode());
			$this->assertSame(['private, no-cache'], $response->getHeader('Cache-Control'));
			$this->assertSame('console.log("panel");', (string) $response->getBody());
		} finally {
			$this->removeDirectory($static);
		}
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

	/** @param array<string, string> $files */
	private function createPanelAssets(array $files): string
	{
		$static = sys_get_temp_dir() . '/cosray-panel-' . bin2hex(random_bytes(8));
		$this->assertTrue(mkdir($static, 0o775, true));

		foreach ($files as $name => $content) {
			$this->assertNotFalse(file_put_contents($static . '/' . $name, $content));
		}

		return $static;
	}

	private function removeDirectory(string $path): void
	{
		if (!is_dir($path)) {
			return;
		}

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}

		rmdir($path);
	}
}
