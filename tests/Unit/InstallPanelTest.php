<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Commands\InstallPanel;
use Cosray\Tests\TestCase;
use ReflectionMethod;
use ReflectionProperty;

final class InstallPanelTest extends TestCase
{
	public function testInstallPathUsesConfiguredPanelPath(): void
	{
		$command = new InstallPanel($this->config([
			'path.public' => '/var/www/public',
			'path.panel' => '/admin',
		]));

		$this->assertSame('/var/www/public/admin/static', $this->invoke($command, 'targetDir'));
	}

	public function testOptionsOverrideInstallPath(): void
	{
		$_SERVER['argv'] = ['cosray-panel', 'install', '--public=public_html', '--panel=panel'];
		$command = new InstallPanel($this->config());
		$cwd = getcwd();
		$this->assertIsString($cwd);

		$this->assertSame($cwd . '/public_html/panel/static', $this->invoke($command, 'targetDir'));
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
