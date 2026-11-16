<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Console\Buffer;
use Celema\Console\Io;
use Celema\Console\Runner;
use Celema\Core\App;
use Celema\Quma\Commands as QumaCommands;
use Celema\Quma\Connection;
use Celema\Router\Router;
use Cosray\Bootstrap;
use Cosray\MigrationFactory;
use Cosray\Tests\Fixtures\Plugin\TestPlugin;
use Cosray\Tests\TestCase;
use PDO;

/**
 * @internal
 *
 * @coversNothing
 */
final class PluginMigrationsTest extends TestCase
{
	private App $app;

	public function testAPluginsMigrationsRunInItsNamespaceAfterDefault(): void
	{
		$connection = $this->connection();

		$this->assertSame(['default', 'test-plugin'], $connection->config->migrationOrder);
		$this->assertArrayHasKey('test-plugin', $connection->config->migrations);
		$this->assertStringNotContainsString(
			'Fixtures/Plugin',
			(string) json_encode($connection->config->migrations['default']),
		);

		[$exit, $output] = $this->migrate($connection, ['run', 'db:migrations']);
		$this->assertSame(0, $exit, $output);
		$this->assertStringContainsString(
			"  - test-plugin:+namespace[pgsql].php\n  - test-plugin:000000-000000-test-plugin.sql\n",
			$output,
		);
	}

	public function testAPluginsTablesGoIntoASchemaOfItsOwnThatCosrayCreates(): void
	{
		// The table references a core table, and only exists if its schema was created first.
		[$exit, $output] = $this->migrate($this->connection(), ['run', 'db:migrations', '--test-run', '--yes']);

		$this->assertSame(0, $exit, $output);
		$this->assertStringContainsString('Rolled back 2 migrations', $output);
		$this->assertSame([], $this->leftovers());
	}

	public function testASiteCanKeepAPluginsTablesInANamePrefix(): void
	{
		$connection = $this->connection([
			'db.placeholders' => ['pgsql' => [
				'cms.prefix' => 'cms.',
				'test-plugin.prefix' => 'test_plugin_',
			]],
		]);

		[$exit, $output] = $this->migrate($connection, ['run', 'db:migrations', '--test-run', '--yes']);

		$this->assertSame(0, $exit, $output);
		$this->assertSame([], $this->leftovers());
	}

	/** @return list<string> what a rolled-back run left behind */
	private function leftovers(): array
	{
		$pdo = new PDO(self::databaseDsn());
		$found = $pdo->query(
			"SELECT nspname FROM pg_namespace WHERE nspname = 'test_plugin'
			UNION ALL SELECT tablename FROM pg_tables WHERE tablename IN ('items', 'test_plugin_items')",
		);
		$this->assertNotFalse($found);

		return $found->fetchAll(PDO::FETCH_COLUMN);
	}

	/**
	 * @param list<string> $argv
	 * @return array{int, string}
	 */
	private function migrate(Connection $connection, array $argv): array
	{
		$buffer = new Buffer();
		$commands = QumaCommands::get($connection, migrationFactory: new MigrationFactory($this->app->container()));
		$exit = new Runner($commands, new Io($buffer))->run($argv);

		return [$exit, $buffer->output() . $buffer->errorOutput()];
	}

	/** @param array<string, mixed> $settings */
	private function connection(array $settings = []): Connection
	{
		$bootstrap = new Bootstrap($this->config([
			'db.dsn' => self::databaseDsn(),
			'path.root' => self::root() . '/tests/Fixtures/Boiler',
			'path.views' => '/templates',
			...$settings,
		]));
		$bootstrap->plugin(TestPlugin::class);
		$this->app = new App($this->factory(), new Router(), $this->container());
		$this->app->load($bootstrap);

		return $this->app->container()->get(Connection::class);
	}
}
