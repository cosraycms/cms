<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Boiler\Renderer as BoilerRenderer;
use Celemas\Cms\Config;
use Celemas\Cms\Plugin;
use Celemas\Cms\Renderer;
use Celemas\Cms\Tests\Fixtures\StaticRenderer;
use Celemas\Cms\Tests\TestCase;
use Celemas\Core\App;
use Celemas\Quma\Connection;
use Celemas\Quma\Delimiters;
use Celemas\Router\Router;

/**
 * @internal
 *
 * @coversNothing
 */
final class PluginTest extends TestCase
{
	public function testConfigProvidesDefaultViewsPath(): void
	{
		$this->assertSame('/views', $this->config()->path->views);
	}

	public function testLoadRegistersDefaultViewRenderer(): void
	{
		$app = $this->loadPlugin();
		$renderer = $app->container()->tag(Renderer::class)->get('view');

		$this->assertInstanceOf(BoilerRenderer::class, $renderer);
		$this->assertSame('<p>plain</p>', trim($renderer->render('plain', [])));
	}

	public function testLoadRegistersConfig(): void
	{
		$app = $this->loadPlugin();

		$this->assertInstanceOf(Config::class, $app->container()->get(Config::class));
	}

	public function testLoadConfiguresDatabasePlaceholders(): void
	{
		$app = $this->loadPlugin();
		$connection = $app->container()->get(Connection::class);
		$placeholders = $connection->config->placeholders;

		$this->assertEquals(Delimiters::comments(), $placeholders?->delimiters());
		$this->assertSame(['cms.prefix' => 'cms.'], $placeholders?->values());
	}

	public function testExplicitViewRendererOverridesDefaultViewRenderer(): void
	{
		$app = $this->loadPlugin(static function (Plugin $plugin): void {
			$plugin->renderer('view', StaticRenderer::class);
		});
		$renderer = $app->container()->tag(Renderer::class)->get('view');

		$this->assertInstanceOf(StaticRenderer::class, $renderer);
		$this->assertSame('custom:plain', $renderer->render('plain', []));
	}

	private function loadPlugin(?callable $configure = null, array $settings = []): App
	{
		$config = $this->config(array_merge([
			'db.dsn' => 'pgsql:dbname=celemas',
			'path.root' => self::root() . '/tests/Fixtures/Boiler',
			'path.views' => '/templates',
		], $settings));
		$plugin = new Plugin($config);

		if ($configure) {
			$configure($plugin);
		}

		$app = new App($this->factory(), new Router(), $this->container());
		$app->load($plugin);

		return $app;
	}
}
