<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Console\Command;
use Celema\Router\Router;
use Celema\Server\FrankenPhp;
use Celema\Server\Server;
use Cosray\App;
use Cosray\Cms;
use Cosray\Console\Commands;
use Cosray\Context;
use Cosray\Locales;
use Cosray\Tests\TestCase;

final class CommandsTest extends TestCase
{
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
		$commands = new Commands($app);
		$commands->add(ScopedCommand::class);
		$entries = $commands->commands()->entries();
		$entry = end($entries);
		$command = $entry->command();

		$this->assertInstanceOf(ScopedCommand::class, $command);
		$this->assertInstanceOf(Cms::class, $command->cms);
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
		$commands = new Commands($app);
		$expected = new FactoryCommand('factory');
		$commands->add([
			FactoryCommand::class => static fn(): FactoryCommand => $expected,
		]);
		$entries = $commands->commands()->entries();
		$entry = end($entries);

		$this->assertSame($expected, $entry->command());
	}

	public function testServerRegistersBothDevServers(): void
	{
		$config = $this->config([
			'db.dsn' => 'sqlite::memory:',
			'error.enabled' => false,
		]);
		$app = new App($config, $this->factory(), new Router(), $this->container());
		$app->boot();
		$commands = new Commands($app);
		$commands->server(port: 8080, watch: ['src/**/*.php'], routePrefix: '/prefix');
		$entries = $commands->commands()->entries();
		$servers = [];

		foreach ($entries as $entry) {
			if (!in_array($entry->meta->full(), ['server', 'frankenphp'], strict: true)) {
				continue;
			}

			$servers[$entry->meta->full()] = $entry->command();
		}

		$this->assertInstanceOf(Server::class, $servers['server']);
		$this->assertInstanceOf(FrankenPhp::class, $servers['frankenphp']);
	}
}

#[Command('test:scoped')]
final class ScopedCommand
{
	public function __construct(
		public readonly Context $context,
		public readonly Cms $cms,
	) {}
}

#[Command('test:factory')]
final class FactoryCommand
{
	public function __construct(
		public readonly string $value,
	) {}
}
