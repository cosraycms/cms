<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Request;
use Cosray\Controller\Panel\Assets;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PanelAssetTest extends TestCase
{
	public function testAssetReturnsNotFoundForPathTraversal(): void
	{
		$panel = new Assets($this->config(), $this->container(), $this->request());

		$this->throws(HttpNotFound::class);
		$panel->asset($this->request(), $this->factory(), '../composer.json');
	}

	public function testAssetReturnsNotModifiedWhenEtagMatches(): void
	{
		$panel = new Assets($this->config(), $this->container(), $this->request());
		$file = self::root() . '/panel/styles/panel.css';
		$etag = md5_file($file);
		$this->assertNotFalse($etag);
		$request = new Request($this->psrRequest()->withHeader('If-None-Match', '"' . $etag . '"'));

		$response = $panel->asset($request, $this->factory(), 'styles/panel.css');

		$this->assertSame(304, $response->getStatusCode());
		$this->assertSame(['private, max-age=3600'], $response->getHeader('Cache-Control'));
		$this->assertSame(['"' . $etag . '"'], $response->getHeader('ETag'));
		$this->assertSame([], $response->getHeader('Content-Type'));
	}

	public function testAssetReturnsCssFileWithCacheHeaders(): void
	{
		$panel = new Assets($this->config(), $this->container(), $this->request());
		$file = self::root() . '/panel/styles/panel.css';
		$etag = md5_file($file);
		$this->assertNotFalse($etag);

		$response = $panel->asset($this->request(), $this->factory(), 'styles/panel.css');

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame(['text/css'], $response->getHeader('Content-Type'));
		$this->assertSame(['private, max-age=3600'], $response->getHeader('Cache-Control'));
		$this->assertSame(['"' . $etag . '"'], $response->getHeader('ETag'));
		$this->assertNotSame([], $response->getHeader('Last-Modified'));
		$this->assertSame(file_get_contents($file), (string) $response->getBody());
	}

	public function testStaticAssetReturnsFileFromPublicPanelStaticDirectory(): void
	{
		$public = $this->createPublicStatic(['panel.js' => 'console.log("panel");']);
		$panel = new Assets(
			$this->config(['path.public' => $public]),
			$this->container(),
			$this->request(),
		);

		try {
			$response = $panel->staticAsset($this->request(), $this->factory(), 'panel.js');

			$this->assertSame(200, $response->getStatusCode());
			$this->assertSame(['private, no-cache'], $response->getHeader('Cache-Control'));
			$this->assertSame('console.log("panel");', (string) $response->getBody());
		} finally {
			$this->removeDirectory($public);
		}
	}

	public function testPanelContextUsesPublicStaticUrls(): void
	{
		$public = $this->createPublicStatic([
			'panel.css' => 'body {}',
			'panel.js' => 'console.log("panel");',
		]);
		$panel = $this->panel(['path.public' => $public]);

		try {
			$context = $panel->data();

			$this->assertContains('/cp/static/panel.css', $context['stylesheets']);
			$this->assertContains('/cp/static/panel.js', $context['moduleScripts']);
		} finally {
			$this->removeDirectory($public);
		}
	}

	public function testPanelContextUsesStaticUrlsInDevelopmentEnv(): void
	{
		$public = $this->createPublicStatic([
			'panel.css' => 'body {}',
			'panel.js' => 'console.log("panel");',
		]);
		$panel = $this->panel(['app.env' => 'development', 'path.public' => $public]);

		try {
			$context = $panel->data();

			$this->assertContains('/cp/static/panel.css', $context['stylesheets']);
			$this->assertContains('/cp/static/panel.js', $context['moduleScripts']);
			$this->assertNotContains('http://localhost:2001/@vite/client', $context['moduleScripts']);
		} finally {
			$this->removeDirectory($public);
		}
	}

	public function testPanelContextUsesViteDevServerWhenPanelDevIsEnabled(): void
	{
		$_SERVER['COSRAY_PANEL_DEV'] = '1';
		$_SERVER['COSRAY_PANEL_DEV_ORIGIN'] = 'http://localhost:2001';
		$panel = $this->panel();

		try {
			$context = $panel->data();

			$this->assertNotContains('/cp/static/panel.css', $context['stylesheets']);
			$this->assertContains('http://localhost:2001/@vite/client', $context['moduleScripts']);
			$this->assertContains('http://localhost:2001/src/panel.ts', $context['moduleScripts']);
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

	/** @param array<string, string> $files */
	private function createPublicStatic(array $files): string
	{
		$public = sys_get_temp_dir() . '/cosray-panel-' . bin2hex(random_bytes(8));
		$static = $public . '/cp/static';
		$this->assertTrue(mkdir($static, 0o775, true));

		foreach ($files as $name => $content) {
			$this->assertNotFalse(file_put_contents($static . '/' . $name, $content));
		}

		return $public;
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
