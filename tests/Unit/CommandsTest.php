<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Core\Server\FrankenPhp;
use Celema\Core\Server\Server;
use Celema\Router\Router;
use Cosray\App;
use Cosray\Console\Commands;
use Cosray\Tests\TestCase;

final class CommandsTest extends TestCase
{
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
