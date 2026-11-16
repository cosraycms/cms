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
use Cosray\Tests\Fixtures\Plugin\TestPlugin;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PluginMigrationsTest extends TestCase
{
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
		$this->assertStringContainsString('  - test-plugin:000000-000000-test-plugin.sql', $output);

		// The plugin's table references a core table, so it sees the core schema.
		[$exit, $output] = $this->migrate($connection, ['run', 'db:migrations', '--test-run', '--yes']);
		$this->assertSame(0, $exit, $output);
		$this->assertStringContainsString('Rolled back 1 migration', $output);
	}

	/**
	 * @param list<string> $argv
	 * @return array{int, string}
	 */
	private function migrate(Connection $connection, array $argv): array
	{
		$buffer = new Buffer();
		$exit = new Runner(QumaCommands::get($connection), new Io($buffer))->run($argv);

		return [$exit, $buffer->output() . $buffer->errorOutput()];
	}

	private function connection(): Connection
	{
		$bootstrap = new Bootstrap($this->config([
			'db.dsn' => self::databaseDsn(),
			'path.root' => self::root() . '/tests/Fixtures/Boiler',
			'path.views' => '/templates',
		]));
		$bootstrap->plugin(TestPlugin::class);
		$app = new App($this->factory(), new Router(), $this->container());
		$app->load($bootstrap);

		return $app->container()->get(Connection::class);
	}
}
