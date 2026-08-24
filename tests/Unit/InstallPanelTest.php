<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Console\BufferedIo;
use Cosray\Commands\InstallPanel;
use Cosray\Tests\TestCase;
use ReflectionMethod;
use ReflectionProperty;

final class InstallPanelTest extends TestCase
{
	public function testInstallPathUsesConfiguredPanelAssetsPath(): void
	{
		$command = new InstallPanel($this->config([
			'path.panelAssets' => '/var/www/panel/static',
		]));

		$this->assertSame('/var/www/panel/static', $this->invoke($command, 'targetDir'));
	}

	public function testOptionOverridesInstallPath(): void
	{
		$_SERVER['argv'] = ['cosray-panel', 'install', '--target=build/panel'];
		$command = new InstallPanel($this->config());
		$cwd = getcwd();
		$this->assertIsString($cwd);

		$this->assertSame($cwd . '/build/panel', $this->invoke($command, 'targetDir'));
	}

	public function testVersionedReleaseUsesCosrayPanelArtifactName(): void
	{
		$command = new InstallPanel($this->config());

		$this->invoke($command, 'preparePanelDownload', '0.3.0');

		$this->assertSame('0.3.0', $this->property($command, 'panelReleaseTag'));
		$this->assertSame('cosray-panel-0.3.0.tar.gz', $this->property($command, 'panelFileName'));
		$this->assertSame(
			'https://cosray.dev/releases/cosray-panel-0.3.0.tar.gz',
			$this->property($command, 'panelUrl'),
		);
	}

	public function testDevelopmentReleaseFallsBackToNightlyArtifact(): void
	{
		$command = new InstallPanel($this->config());

		$this->invoke($command, 'preparePanelDownload', 'dev-main');

		$this->assertSame('nightly', $this->property($command, 'panelReleaseTag'));
		$this->assertSame('cosray-panel-nightly.tar.gz', $this->property($command, 'panelFileName'));
	}

	public function testPanelValidationRequiresHtmxAsset(): void
	{
		$dir = $this->createPanelDir([
			'cosray-panel.json' => '{}',
			'panel.css' => 'body {}',
			'panel.js' => 'console.log("panel");',
		]);
		$command = new InstallPanel($this->config());

		try {
			$this->throws(\RuntimeException::class, 'Panel archive is missing htmx.js');
			$this->invoke($command, 'validatePanel', $dir);
		} finally {
			$this->removeDirectory($dir);
		}
	}

	public function testWarnsAboutStaleAssetsLeftAtThePanelPath(): void
	{
		$public = $this->createLegacyPanelDir();
		$command = new InstallPanel($this->config([
			'path.public' => $public,
			'path.panel' => '/cp',
			'path.panelAssets' => $public . '/../panel/static',
		]));
		$io = new BufferedIo();
		new ReflectionProperty($command, 'io')->setValue($command, $io);

		try {
			$this->invoke($command, 'warnAboutLegacyDir');

			$this->assertStringContainsString("{$public}/cp/static", $io->errorOutput());
		} finally {
			$this->removeDirectory($public);
		}
	}

	public function testStaysQuietWhenNoStaleAssetsRemain(): void
	{
		$command = new InstallPanel($this->config([
			'path.public' => sys_get_temp_dir() . '/cosray-missing-' . bin2hex(random_bytes(8)),
		]));
		$io = new BufferedIo();
		new ReflectionProperty($command, 'io')->setValue($command, $io);

		$this->invoke($command, 'warnAboutLegacyDir');

		$this->assertSame('', $io->errorOutput());
	}

	private function createLegacyPanelDir(): string
	{
		$public = sys_get_temp_dir() . '/cosray-legacy-' . bin2hex(random_bytes(8));
		$this->assertTrue(mkdir($public . '/cp/static', 0o775, true));

		return $public;
	}

	/** @param array<string, string> $files */
	private function createPanelDir(array $files): string
	{
		$dir = sys_get_temp_dir() . '/cosray-panel-' . bin2hex(random_bytes(8));
		$this->assertTrue(mkdir($dir, 0o775, true));

		foreach ($files as $name => $content) {
			$this->assertNotFalse(file_put_contents("{$dir}/{$name}", $content));
		}

		return $dir;
	}

	private function removeDirectory(string $path): void
	{
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}

		rmdir($path);
	}

	/** @param mixed ...$args */
	private function invoke(object $object, string $name, mixed ...$args): mixed
	{
		$method = new ReflectionMethod($object, $name);

		return $method->invoke($object, ...$args);
	}

	private function property(object $object, string $name): mixed
	{
		$property = new ReflectionProperty($object, $name);

		return $property->getValue($object);
	}
}
