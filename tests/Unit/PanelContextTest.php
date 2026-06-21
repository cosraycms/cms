<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Controller\Panel\Index;
use Cosray\Navigation;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PanelContextTest extends TestCase
{
	public function testPanelAssetsUseCacheBustingUrls(): void
	{
		$container = $this->container();
		$container->add(Navigation::class, new Navigation())->value();
		$panel = new Index($this->config(), $container, $this->request());

		$context = $panel->index();

		$this->assertContainsUrlWithVersion('/cp/assets/app/panel.js', $context['scripts']);
		$this->assertContainsUrlWithVersion('/cp/assets/styles/app.css', $context['stylesheets']);
	}

	/** @param list<string> $urls */
	private function assertContainsUrlWithVersion(string $path, array $urls): void
	{
		foreach ($urls as $url) {
			if (!str_starts_with($url, $path . '?v=')) {
				continue;
			}

			$this->assertMatchesRegularExpression('/^' . preg_quote($path, '/') . '\\?v=[a-f0-9]+$/', $url);

			return;
		}

		$this->fail('No cache-busted URL found for ' . $path);
	}
}
