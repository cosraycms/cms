<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Console\Buffer;
use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Quma\Database;
use Celema\Router\Router;
use Cosray\App;
use Cosray\Cms;
use Cosray\Commands\Fulltext;
use Cosray\Console\Commands;
use Cosray\Console\Runtime;
use Cosray\Context;
use Cosray\Locales;
use Cosray\Tests\TestCase;

final class CommandsTest extends TestCase
{
	public function testStandaloneRunnerListsCommandsWithoutOpeningTheDatabase(): void
	{
		$process = proc_open(
			[PHP_BINARY, self::root() . '/run', 'commands'],
			[1 => ['pipe', 'w'], 2 => ['redirect', 1]],
			$pipes,
			self::root(),
			['COSRAY_DB_HOST' => 'not-a-database.invalid'],
		);
		$this->assertIsResource($process);
		$output = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		$this->assertSame(0, proc_close($process), $output);
		$this->assertStringContainsString('db:migrations', $output);
	}

	public function testClassStringsAreAutowiredInConsoleScope(): void
	{
		$config = $this->config([
			'db.dsn' => 'sqlite::memory:',
			'error.enabled' => false,
		]);
		$app = new App($config, $this->factory(), new Router(), $this->container());
		$locales = new Locales();
		$locales->add('en', title: 'English');
		$app->load($locales);
		$buffer = new Buffer();
		$commands = new Commands($app, new Io($buffer));
		$commands->add(ScopedCommand::class);

		$this->assertSame(0, $commands->runner()->run(['run', 'test:scoped']), $buffer->errorOutput());
		$command = ScopedCommand::$invoked;
		$this->assertInstanceOf(ScopedCommand::class, $command);
		$this->assertInstanceOf(Cms::class, $command->cms);
		$this->assertSame($app, $command->app);
		$this->assertNull($command->context->request);
		$this->assertSame('en', $command->context->localeId());
	}

	public function testKeyedFactoriesStaySupported(): void
	{
		$config = $this->config([
			'db.dsn' => 'sqlite::memory:',
			'error.enabled' => false,
		]);
		$app = new App($config, $this->factory(), new Router(), $this->container());
		$buffer = new Buffer();
		$commands = new Commands($app, new Io($buffer));
		$expected = new FactoryCommand('factory');
		$commands->add([
			FactoryCommand::class => static fn(): FactoryCommand => $expected,
		]);

		$this->assertSame(0, $commands->runner()->run(['run', 'test:factory']), $buffer->errorOutput());
		$this->assertSame($expected, FactoryCommand::$invoked);
	}

	public function testFulltextResolvesInConsoleScopeWithoutOpeningTheDatabase(): void
	{
		$config = $this->config([
			'db.dsn' => 'pgsql:host=not-a-database.invalid;dbname=missing',
			'error.enabled' => false,
		]);
		$app = new App($config, $this->factory(), new Router(), $this->container());
		$locales = new Locales();
		$locales->add('en', 'English', pgDict: 'english');
		$app->load($locales);
		$buffer = new Buffer();
		$commands = new Commands($app, new Io($buffer));

		$this->assertSame(0, $commands->runner()->run(['run', 'help', 'db:fulltext']));
		$this->assertStringContainsString('db:fulltext', $buffer->output());
		$this->assertInstanceOf(Fulltext::class, new Runtime($app)->get(Fulltext::class));
		$this->assertFalse($app->container()->get(Database::class)->connected());
	}

	public function testPanelPublishIsAvailableWithoutTheDatabase(): void
	{
		$config = $this->config([
			'db.dsn' => 'pgsql:host=not-a-database.invalid;dbname=missing',
			'error.enabled' => false,
		]);
		$app = new App($config, $this->factory(), new Router(), $this->container());
		$buffer = new Buffer();
		$commands = new Commands($app, new Io($buffer));

		$this->assertSame(0, $commands->runner()->run(['run', 'help', 'panel:publish']));
		$this->assertStringContainsString('panel:publish', $buffer->output());
		$this->assertFalse($app->container()->get(Database::class)->connected());
	}

	public function testServerRegistersTheDevelopmentCommands(): void
	{
		$config = $this->config([
			'db.dsn' => 'sqlite::memory:',
			'error.enabled' => false,
		]);
		$app = new App($config, $this->factory(), new Router(), $this->container());
		$app->boot();
		$buffer = new Buffer();
		$commands = new Commands($app, new Io($buffer));
		$commands->server(port: 8080, watch: ['src/**/*.php'], routePrefix: '/prefix');
		$commands->runner()->run(['run', 'commands']);

		$names = explode("\n", $buffer->output());

		$this->assertContains('server', $names);
		$this->assertContains('reload', $names);
		$this->assertContains('frankenphp:install', $names);
	}
}

#[Command('test:scoped')]
final class ScopedCommand
{
	public static ?self $invoked = null;

	public function __construct(
		public readonly Context $context,
		public readonly Cms $cms,
		public readonly App $app,
	) {}

	public function __invoke(): int
	{
		self::$invoked = $this;

		return 0;
	}
}

#[Command('test:factory')]
final class FactoryCommand
{
	public static ?self $invoked = null;

	public function __construct(
		public readonly string $value,
	) {}

	public function __invoke(): int
	{
		self::$invoked = $this;

		return 0;
	}
}
