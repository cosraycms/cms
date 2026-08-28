<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use ArrayObject;
use Celema\Core\App;
use Celema\Quma\Connection;
use Celema\Router\Router;
use Cosray\Bootstrap;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Index as FieldIndex;
use Cosray\Field\Schema\Registry as FieldSchemas;
use Cosray\Panel\Dashboard;
use Cosray\Plugin\Plugin;
use Cosray\Plugin\Registrar;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;
use Cosray\Tests\Fixtures\Field\TestMoney;
use Cosray\Tests\Fixtures\Node\PlainPage;
use Cosray\Tests\Fixtures\Plugin\TestBadge;
use Cosray\Tests\Fixtures\Plugin\TestBadgeHandler;
use Cosray\Tests\Fixtures\Plugin\TestDashboardCard;
use Cosray\Tests\Fixtures\Plugin\TestParameterizedPlugin;
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

		$controls = $container->get(\Cosray\Field\Control\Registry::class);
		$this->assertTrue($controls->has('test-money-picker'));
		// Plugin-relative module paths are prefixed with the plugin id.
		$this->assertSame(
			['tag' => 'test-money-picker', 'module' => 'test-plugin/controls.js'],
			$controls->get('test-money-picker'),
		);

		$dashboard = $container->get(Dashboard::class);
		$this->assertContains(TestDashboardCard::class, $dashboard->cards());
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

	public function testOptionReadsTheNamespacedConfigKey(): void
	{
		$seen = new ArrayObject();

		$this->loadBootstrap(
			static function (Bootstrap $bootstrap) use ($seen): void {
				$bootstrap->plugin(new class($seen) implements Plugin {
					public function __construct(
						private readonly ArrayObject $seen,
					) {}

					public function id(): string
					{
						return 'acme-shop';
					}

					public function register(Registrar $cms): void
					{
						$this->seen['configured'] = $cms->option('currency', 'EUR');
						$this->seen['defaulted'] = $cms->option('locale', 'de');
						$this->seen['absent'] = $cms->option('absent');
					}
				});
			},
			['acme-shop.currency' => 'USD'],
		);

		$this->assertSame('USD', $seen['configured']);
		$this->assertSame('de', $seen['defaulted']);
		$this->assertNull($seen['absent']);
	}

	public function testDashlessPluginIdThrows(): void
	{
		$this->throws(RuntimeException::class, "Invalid plugin id 'shop'");

		$this->loadBootstrap(static function (Bootstrap $bootstrap): void {
			$bootstrap->plugin(new class implements Plugin {
				public function id(): string
				{
					return 'shop';
				}

				public function register(Registrar $cms): void {}
			});
		});
	}

	public function testMalformedPluginIdThrows(): void
	{
		$this->throws(RuntimeException::class, "Invalid plugin id 'Acme-Shop'");

		$this->loadBootstrap(static function (Bootstrap $bootstrap): void {
			$bootstrap->plugin(new class implements Plugin {
				public function id(): string
				{
					return 'Acme-Shop';
				}

				public function register(Registrar $cms): void {}
			});
		});
	}

	public function testConstructorParametersFailWithGuidance(): void
	{
		$this->throws(RuntimeException::class, 'must be constructible without arguments');

		$this->loadBootstrap(static function (Bootstrap $bootstrap): void {
			$bootstrap->plugin(TestParameterizedPlugin::class);
		});
	}

	public function testParameterizedPluginLoadsAsPrebuiltInstance(): void
	{
		$app = $this->loadBootstrap(static function (Bootstrap $bootstrap): void {
			$bootstrap->plugin(new TestParameterizedPlugin('CHF'));
		});

		$service = $app->container()->get('test-parameterized.currency');
		$this->assertSame('CHF', $service->currency);
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
