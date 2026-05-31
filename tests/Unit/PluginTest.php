<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celemas\Core\App;
use Celemas\Quma\Connection;
use Celemas\Quma\Delimiters;
use Celemas\Router\Router;
use Cosray\Config;
use Cosray\Plugin;
use Cosray\Renderer;
use Cosray\Tests\Fixtures\StaticRenderer;
use Cosray\Tests\TestCase;
use Cosray\View\Boiler\Renderer as BoilerRenderer;

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
		$this->assertSame(['cms.prefix' => 'cms.', 'cms.obj' => ''], $placeholders?->values());
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
			'db.dsn' => 'pgsql:dbname=cosray',
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
