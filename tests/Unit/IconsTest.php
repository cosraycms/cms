<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Config;
use Celemas\Cms\Contract\Icons as IconsContract;
use Celemas\Cms\Icons;
use Celemas\Cms\Icons\Iconify;
use Celemas\Cms\Icons\Local;
use Celemas\Cms\Tests\TestCase;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class IconsTest extends TestCase
{
	public function testCacheMissFetchesAndStoresSvg(): void
	{
		$publicDir = $this->publicDir();
		$calls = 0;

		try {
			$icons = $this->icons(
				$publicDir,
				function (string $url, int $timeout, string $userAgent) use (&$calls): string {
					$calls++;
					$this->assertSame('https://api.iconify.design/bi/check.svg', $url);
					$this->assertSame(5, $timeout);
					$this->assertSame('celemas/cms', $userAgent);

					return '<svg viewBox="0 0 16 16"></svg>';
				},
			);
			$svg = $icons->icon('bi:check');
			$cacheFile = $publicDir . '/cache/icons/' . hash('xxh3', 'bi:check') . '.svg';

			$this->assertSame(1, $calls);
			$this->assertSame('<svg viewBox="0 0 16 16"></svg>', $svg);
			$this->assertFileExists($cacheFile);
			$this->assertSame('<svg viewBox="0 0 16 16"></svg>', file_get_contents($cacheFile));
		} finally {
			$this->removeDir($publicDir);
		}
	}

	public function testCacheHitSkipsFetch(): void
	{
		$publicDir = $this->publicDir();
		$calls = 0;

		try {
			$cacheDir = $publicDir . '/cache/icons';
			mkdir($cacheDir, 0o755, true);
			$cacheFile = $cacheDir . '/' . hash('xxh3', 'bi:check') . '.svg';
			file_put_contents($cacheFile, '<svg data-cache="1"></svg>');

			$icons = $this->icons(
				$publicDir,
				static function () use (&$calls): ?string {
					$calls++;

					return null;
				},
			);
			$svg = $icons->icon('bi:check');

			$this->assertSame(0, $calls);
			$this->assertSame('<svg data-cache="1"></svg>', $svg);
		} finally {
			$this->removeDir($publicDir);
		}
	}

	public function testUnprefixedIconWithoutLocalPathReturnsEmptyStringWithoutFetch(): void
	{
		$publicDir = $this->publicDir();
		$calls = 0;

		try {
			$icons = $this->icons(
				$publicDir,
				static function () use (&$calls): string {
					$calls++;

					return '<svg></svg>';
				},
			);

			$this->assertSame('', $icons->icon('invalid'));
			$this->assertSame(0, $calls);
		} finally {
			$this->removeDir($publicDir);
		}
	}

	public function testPrefixedLocalIconTakesPriorityOverRemote(): void
	{
		$publicDir = $this->publicDir();
		$calls = 0;
		$iconsPath = $publicDir . '/custom-icons';
		$this->writeSvg($iconsPath . '/bi/check.svg', '<svg data-source="local-prefix"></svg>');

		try {
			$icons = $this->icons(
				$publicDir,
				static function () use (&$calls): string {
					$calls++;

					return '<svg data-source="remote"></svg>';
				},
				['icons.local.paths' => [$iconsPath]],
			);
			$svg = $icons->icon('bi:check');

			$this->assertSame(0, $calls);
			$this->assertStringContainsString('data-source="local-prefix"', $svg);
		} finally {
			$this->removeDir($publicDir);
		}
	}

	public function testUnprefixedLocalIconResolvesFromCustomPathRoot(): void
	{
		$publicDir = $this->publicDir();
		$calls = 0;
		$iconsPath = $publicDir . '/custom-icons';
		$this->writeSvg($iconsPath . '/logo.svg', '<svg data-source="local-root"></svg>');

		try {
			$icons = $this->icons(
				$publicDir,
				static function () use (&$calls): string {
					$calls++;

					return '<svg data-source="remote"></svg>';
				},
				['icons.local.paths' => [$iconsPath]],
			);
			$svg = $icons->icon('logo');

			$this->assertSame(0, $calls);
			$this->assertStringContainsString('data-source="local-root"', $svg);
		} finally {
			$this->removeDir($publicDir);
		}
	}

	public function testCustomPathsUseFirstMatch(): void
	{
		$publicDir = $this->publicDir();
		$calls = 0;
		$first = $publicDir . '/custom-icons-1';
		$second = $publicDir . '/custom-icons-2';
		$this->writeSvg($first . '/logo.svg', '<svg data-source="first"></svg>');
		$this->writeSvg($second . '/logo.svg', '<svg data-source="second"></svg>');

		try {
			$icons = $this->icons(
				$publicDir,
				static function () use (&$calls): string {
					$calls++;

					return '<svg data-source="remote"></svg>';
				},
				['icons.local.paths' => [$first, $second]],
			);
			$svg = $icons->icon('logo');

			$this->assertSame(0, $calls);
			$this->assertStringContainsString('data-source="first"', $svg);
		} finally {
			$this->removeDir($publicDir);
		}
	}

	public function testPrefixedIconFallsBackToRemoteWhenCustomPathDoesNotMatch(): void
	{
		$publicDir = $this->publicDir();
		$calls = 0;
		$iconsPath = $publicDir . '/custom-icons';
		mkdir($iconsPath, 0o755, true);

		try {
			$icons = $this->icons(
				$publicDir,
				function (string $url, int $timeout, string $userAgent) use (&$calls): string {
					$calls++;
					$this->assertSame('https://api.iconify.design/bi/check.svg', $url);
					$this->assertSame(5, $timeout);
					$this->assertSame('celemas/cms', $userAgent);

					return '<svg data-source="remote"></svg>';
				},
				['icons.local.paths' => [$iconsPath]],
			);
			$svg = $icons->icon('bi:check');

			$this->assertSame(1, $calls);
			$this->assertStringContainsString('data-source="remote"', $svg);
		} finally {
			$this->removeDir($publicDir);
		}
	}

	public function testIconifyPassesArgsAsQueryString(): void
	{
		$publicDir = $this->publicDir();
		$calls = 0;

		try {
			$icons = $this->icons(
				$publicDir,
				function (string $url, int $timeout, string $userAgent) use (&$calls): string {
					$calls++;
					$this->assertSame(
						'https://api.iconify.design/bi/check.svg?color=%23ff0000&height=24&width=24',
						$url,
					);
					$this->assertSame(5, $timeout);
					$this->assertSame('celemas/cms', $userAgent);

					return '<svg data-source="remote"></svg>';
				},
			);
			$svg = $icons->icon('bi:check', [
				'width' => 24,
				'color' => '#ff0000',
				'height' => 24,
			]);

			$this->assertSame(1, $calls);
			$this->assertSame('<svg data-source="remote"></svg>', $svg);
		} finally {
			$this->removeDir($publicDir);
		}
	}

	public function testIconifyCacheIncludesArgs(): void
	{
		$publicDir = $this->publicDir();
		$urls = [];

		try {
			$icons = $this->icons(
				$publicDir,
				static function (string $url) use (&$urls): string {
					$urls[] = $url;

					return '<svg data-call="' . count($urls) . '"></svg>';
				},
			);
			$red = $icons->icon('bi:check', ['color' => 'red']);
			$blue = $icons->icon('bi:check', ['color' => 'blue']);
			$redAgain = $icons->icon('bi:check', ['color' => 'red']);

			$this->assertSame('<svg data-call="1"></svg>', $red);
			$this->assertSame('<svg data-call="2"></svg>', $blue);
			$this->assertSame($red, $redAgain);
			$this->assertSame(
				[
					'https://api.iconify.design/bi/check.svg?color=red',
					'https://api.iconify.design/bi/check.svg?color=blue',
				],
				$urls,
			);
		} finally {
			$this->removeDir($publicDir);
		}
	}

	public function testInvalidResponseReturnsEmptyString(): void
	{
		$publicDir = $this->publicDir();

		try {
			$icons = $this->icons(
				$publicDir,
				static fn(): string => 'not-svg',
			);
			$cacheFile = $publicDir . '/cache/icons/' . hash('xxh3', 'bi:check') . '.svg';

			$this->assertSame('', $icons->icon('bi:check'));
			$this->assertFileDoesNotExist($cacheFile);
		} finally {
			$this->removeDir($publicDir);
		}
	}

	public function testLocalIconAddsClassStyleAndColorToSvgTag(): void
	{
		$publicDir = $this->publicDir();
		$calls = 0;
		$iconsPath = $publicDir . '/custom-icons';
		$this->writeSvg($iconsPath . '/logo.svg', '<svg class="base" style="display: block"></svg>');

		try {
			$icons = $this->icons(
				$publicDir,
				static function () use (&$calls): string {
					$calls++;

					return '<svg></svg>';
				},
				['icons.local.paths' => [$iconsPath]],
			);
			$svg = $icons->icon('logo', [
				'color' => '#ff0000',
				'class' => 'extra',
				'style' => 'height: 2rem',
			]);

			$this->assertSame(0, $calls);
			$this->assertStringContainsString('class="base extra"', $svg);
			$this->assertStringContainsString('display: block', $svg);
			$this->assertStringContainsString('height: 2rem', $svg);
			$this->assertStringContainsString('color: #ff0000', $svg);
		} finally {
			$this->removeDir($publicDir);
		}
	}

	public function testCacheDirectoryIsCreatedAutomatically(): void
	{
		$publicDir = $this->publicDir();

		try {
			$icons = $this->icons(
				$publicDir,
				static fn(): string => '<svg></svg>',
			);
			$icons->icon('bi:check');

			$this->assertDirectoryExists($publicDir . '/cache/icons');
		} finally {
			$this->removeDir($publicDir);
		}
	}

	private function icons(string $publicDir, callable $fetch, array $settings = []): Icons
	{
		$config = $this->config(array_merge([
			'path.public' => $publicDir,
			'path.cache' => '/cache',
		], $settings));
		$paths = $config->icons->localPaths;
		$container = $this->container();
		$container->add(Config::class, $config);
		$container->tag(IconsContract::class)->add('local', new Local(is_array($paths) ? $paths : []));
		$container->tag(IconsContract::class)->add('iconify', new Iconify($config, $fetch));

		return new Icons($container, $config);
	}

	private function writeSvg(string $file, string $svg): void
	{
		$dir = dirname($file);
		mkdir($dir, 0o755, true);
		file_put_contents($file, $svg);
	}

	private function publicDir(): string
	{
		$dir = sys_get_temp_dir() . '/celemas-cms-icons-' . bin2hex(random_bytes(8));
		mkdir($dir, 0o755, true);

		return $dir;
	}

	private function removeDir(string $path): void
	{
		if (!is_dir($path)) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($iterator as $item) {
			if ($item->isDir()) {
				rmdir($item->getPathname());
				continue;
			}

			unlink($item->getPathname());
		}

		rmdir($path);
	}
}
