<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class OldPanelRoutesTest extends End2EndTestCase
{
	private ?string $publicPath = null;

	protected function tearDown(): void
	{
		if ($this->publicPath !== null) {
			$this->removeDirectory($this->publicPath);
			$this->publicPath = null;
		}

		parent::tearDown();
	}

	public function testLegacyPanelFrontendRoutesUseFixedPanelPath(): void
	{
		$this->publicPath = $this->createLegacyPanelPublicPath();
		$this->app = $this->createApp([
			'path.panel' => '/admin',
			'path.public' => $this->publicPath,
		]);

		$index = $this->makeRequest('GET', '/panel');
		$indexWithSlash = $this->makeRequest('GET', '/panel/');
		$asset = $this->makeRequest('GET', '/panel/assets/app.js');

		$this->assertResponseOk($index);
		$this->assertResponseOk($indexWithSlash);
		$this->assertResponseOk($asset);
		$this->assertSame('legacy panel index', (string) $index->getBody());
		$this->assertSame('legacy panel index', (string) $indexWithSlash->getBody());
		$this->assertSame('legacy asset', (string) $asset->getBody());
	}

	public function testLegacyPanelBootRouteUsesFixedPanelPath(): void
	{
		$this->app = $this->createApp(['path.panel' => '/admin']);

		$response = $this->makeRequest('GET', '/panel/boot');
		$payload = $this->assertJsonResponse($response, 200);

		$this->assertSame('en', $payload['defaultLocale'] ?? null);
	}

	private function createLegacyPanelPublicPath(): string
	{
		$path = sys_get_temp_dir() . '/cosray-legacy-panel-' . bin2hex(random_bytes(4));

		if (!mkdir($path . '/panel/assets', 0o775, true) && !is_dir($path . '/panel/assets')) {
			$this->fail('Failed to create temporary legacy panel directory');
		}

		file_put_contents($path . '/panel/index.html', 'legacy panel index');
		file_put_contents($path . '/panel/assets/app.js', 'legacy asset');

		return $path;
	}

	private function removeDirectory(string $path): void
	{
		if (!is_dir($path)) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($iterator as $file) {
			if ($file->isDir()) {
				rmdir($file->getPathname());
			} else {
				unlink($file->getPathname());
			}
		}

		rmdir($path);
	}
}
