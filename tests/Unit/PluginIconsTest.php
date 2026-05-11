<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Contract\Icons as IconsContract;
use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Plugin;
use Celemas\Cms\Tests\TestCase;
use ReflectionProperty;

final class PluginIconsTest extends TestCase
{
	public function testIconsPrependsProvidersInCustomRegistry(): void
	{
		$plugin = new Plugin($this->config());
		$first = $this->provider();
		$second = $this->provider();
		$plugin->icons($first);
		$plugin->icons($second);
		$providers = $this->customProviders($plugin);

		$this->assertSame($second, $providers[0]);
		$this->assertSame($first, $providers[1]);
		$this->assertFalse($this->replacesDefaultProviders($plugin));
	}

	public function testIconsReplaceResetsRegistryAndStaysActive(): void
	{
		$plugin = new Plugin($this->config());
		$first = $this->provider();
		$second = $this->provider();
		$third = $this->provider();
		$plugin->icons($first);
		$plugin->icons($second, replace: true);
		$plugin->icons($third);
		$providers = $this->customProviders($plugin);

		$this->assertCount(2, $providers);
		$this->assertSame($third, $providers[0]);
		$this->assertSame($second, $providers[1]);
		$this->assertTrue($this->replacesDefaultProviders($plugin));
	}

	public function testIconsRejectsInvalidClassString(): void
	{
		$plugin = new Plugin($this->config());
		$this->throws(RuntimeException::class, 'Icons providers must implement ' . IconsContract::class);
		$plugin->icons(self::class);
	}

	private function provider(): IconsContract
	{
		return new class implements IconsContract {
			public function icon(string $id, array $args = []): string
			{
				return '';
			}
		};
	}

	private function customProviders(Plugin $plugin): array
	{
		$property = new ReflectionProperty($plugin, 'customIconProviders');

		return $property->getValue($plugin);
	}

	private function replacesDefaultProviders(Plugin $plugin): bool
	{
		$property = new ReflectionProperty($plugin, 'replaceDefaultIconProviders');

		return $property->getValue($plugin);
	}
}
