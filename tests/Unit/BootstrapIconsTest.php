<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Bootstrap;
use Cosray\Contract\Icons as IconsContract;
use Cosray\Exception\RuntimeException;
use Cosray\Tests\TestCase;
use ReflectionProperty;

final class BootstrapIconsTest extends TestCase
{
	public function testIconsPrependsProvidersInCustomRegistry(): void
	{
		$plugin = new Bootstrap($this->config());
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
		$plugin = new Bootstrap($this->config());
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
		$plugin = new Bootstrap($this->config());
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

	private function customProviders(Bootstrap $plugin): array
	{
		$property = new ReflectionProperty($plugin, 'customIconProviders');

		return $property->getValue($plugin);
	}

	private function replacesDefaultProviders(Bootstrap $plugin): bool
	{
		$property = new ReflectionProperty($plugin, 'replaceDefaultIconProviders');

		return $property->getValue($plugin);
	}
}
