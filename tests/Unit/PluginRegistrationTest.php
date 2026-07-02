<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celemas\Core\App;
use Celemas\Quma\Connection;
use Celemas\Router\Router;
use Cosray\Bootstrap;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Index as FieldIndex;
use Cosray\Field\Schema\Registry as FieldSchemas;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;
use Cosray\Tests\Fixtures\Field\TestMoney;
use Cosray\Tests\Fixtures\Node\PlainPage;
use Cosray\Tests\Fixtures\Plugin\TestBadge;
use Cosray\Tests\Fixtures\Plugin\TestBadgeHandler;
use Cosray\Tests\Fixtures\Plugin\TestPlugin;
use Cosray\Tests\TestCase;
use stdClass;

/**
 * @internal
 *
 * @coversNothing
 */
final class PluginRegistrationTest extends TestCase
{
	public function testExplicitPluginRegistration(): void
	{
		$app = $this->loadBootstrap(static function (Bootstrap $bootstrap): void {
			$bootstrap->plugin(TestPlugin::class);
		});
		$container = $app->container();

		$index = $container->get(FieldIndex::class);
		$this->assertSame(TestMoney::class, $index->resolve('money'));

		$schemas = $container->get(FieldSchemas::class);
		$this->assertInstanceOf(TestBadgeHandler::class, $schemas->getHandler(new TestBadge('new')));

		$node = $container->tag(Bootstrap::NODE_TAG)->entry('plain-page')->definition();
		$this->assertSame(PlainPage::class, $node);

		$this->assertInstanceOf(stdClass::class, $container->get('test-plugin.service'));

		$this->assertSame('/test-plugin', $app->router()->url('test-plugin.route'));

		$assets = $container->get(\Cosray\Plugin\Assets::class);
		$this->assertNotNull($assets->dir('test-plugin'));
		$this->assertFileExists($assets->dir('test-plugin') . '/controls.js');
		$this->assertNull($assets->dir('unknown'));

		$blocks = $container->get(\Cosray\Block\Registry::class);
		$this->assertTrue($blocks->has('test-notice'));
		$this->assertSame('element', $blocks->get('test-notice')->control()->array()['name']);
	}

	public function testPluginRegistersCollection(): void
	{
		$app = $this->loadBootstrap(static function (Bootstrap $bootstrap): void {
			$bootstrap->plugin(new TestPlugin());
		});

		$collection = $app
			->container()
			->tag(\Cosray\Collection::class)
			->entry('test-articles')
			->definition();
		$this->assertSame(TestArticlesCollection::class, $collection);
	}

	public function testPluginMigrationAndSqlDirs(): void
	{
		$app = $this->loadBootstrap(static function (Bootstrap $bootstrap): void {
			$bootstrap->plugin(TestPlugin::class);
		});

		$config = $app->container()->get(Connection::class)->config;
		$migrations = $config->migrations;
		$dirs = [];
		array_walk_recursive($migrations, static function ($dir) use (&$dirs): void {
			$dirs[] = $dir;
		});
		$this->assertTrue(
			array_any($dirs, static fn($dir) => str_contains(
				(string) $dir,
				'Fixtures/Plugin/db/migrations',
			)),
		);

		$sql = $config->sql;
		$sqlDirs = [];
		array_walk_recursive($sql, static function ($dir) use (&$sqlDirs): void {
			$sqlDirs[] = $dir;
		});
		$this->assertTrue(
			array_any($sqlDirs, static fn($dir) => str_contains((string) $dir, 'Fixtures/Plugin/db/sql')),
		);
	}

	public function testConfigPluginRegistration(): void
	{
		$app = $this->loadBootstrap(settings: ['plugins' => [TestPlugin::class]]);

		$index = $app->container()->get(FieldIndex::class);
		$this->assertSame(TestMoney::class, $index->resolve('money'));
	}

	public function testDuplicatePluginIdThrows(): void
	{
		$this->throws(RuntimeException::class, 'Duplicate plugin id');

		$this->loadBootstrap(static function (Bootstrap $bootstrap): void {
			$bootstrap->plugin(TestPlugin::class);
			$bootstrap->plugin(new TestPlugin());
		});
	}

	public function testInvalidPluginClassThrows(): void
	{
		$this->throws(RuntimeException::class, 'must implement');

		$this->loadBootstrap(static function (Bootstrap $bootstrap): void {
			$bootstrap->plugin(stdClass::class);
		});
	}

	private function loadBootstrap(?callable $configure = null, array $settings = []): App
	{
		$config = $this->config(array_merge([
			'db.dsn' => 'pgsql:dbname=cosray',
			'path.root' => self::root() . '/tests/Fixtures/Boiler',
			'path.views' => '/templates',
		], $settings));
		$bootstrap = new Bootstrap($config);

		if ($configure) {
			$configure($bootstrap);
		}

		$app = new App($this->factory(), new Router(), $this->container());
		$app->load($bootstrap);

		return $app;
	}
}
