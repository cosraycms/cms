<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Commands\InstallPanel;
use Cosray\Tests\TestCase;
use ReflectionProperty;

final class InstallPanelTest extends TestCase
{
	public function testInstallPathIgnoresConfiguredPanelPath(): void
	{
		$command = new InstallPanel($this->config([
			'path.public' => '/var/www/public',
			'path.panel' => '/admin',
		]));

		$this->assertSame('/var/www/public/panel', $this->property($command, 'publicPath'));
	}

	private function property(object $object, string $name): mixed
	{
		$property = new ReflectionProperty($object, $name);

		return $property->getValue($object);
	}
}
