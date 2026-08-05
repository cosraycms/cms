<?php

declare(strict_types=1);

namespace Cosray\Console;

use Celema\Console\Commands as BaseCommands;
use Celema\Console\Runner;
use Celema\Container\Container;
use Celema\Quma\Commands as QumaCommands;
use Celema\Quma\Connection;
use Celema\Server\FrankenPhp;
use Celema\Server\Server;
use Celema\Server\Setup;
use Celema\Verba\Command\StatusCommand;
use Celema\Verba\Command\SyncCommand;
use Celema\Verba\Tool\Domain;
use Celema\Verba\Tool\PhpScanner;
use Closure;
use Cosray\App;
use Cosray\Commands\Fulltext;
use Cosray\Commands\InstallPanel;
use Cosray\Commands\RecreateSortIndex;
use Cosray\Commands\References;
use Cosray\Commands\Superuser;
use Cosray\Commands\Titles;
use Cosray\I18n\SchemaScanner;
use Cosray\MigrationFactory;

/**
 * The base CLI command set of a Cosray application.
 *
 * Boots the app and bundles the quma migration commands and Cosray's own
 * commands as lazy factories. `server()` and `i18n()` register the per-app
 * dev server and translation commands; application command class-strings
 * are lazily autowired from one request-free console scope.
 *
 *     $commands = new Commands($app);
 *     $commands->server(port: 6913, watch: ['src/**\/*.php']);
 *     $commands->i18n('mysite', locales: ['de', 'en']);
 *     $commands->add(ImportCommand::class);
 *
 *     return $commands->runner();
 *
 * @api
 */
final class Commands
{
	private readonly BaseCommands $commands;
	private ?Runtime $runtime = null;

	public function __construct(
		private readonly App $app,
	) {
		$app->boot();
		$container = $app->container();

		$this->commands = QumaCommands::get(
			$this->conn(),
			migrationFactory: new MigrationFactory($container),
		);
		$this->commands->add([
			Fulltext::class => fn(): Fulltext => new Fulltext($this->conn()),
			References::class => fn(): References => new References($this->conn()),
			RecreateSortIndex::class => fn(): RecreateSortIndex => new RecreateSortIndex($this->conn()),
			Superuser::class => fn(): Superuser => new Superuser($this->conn()),
			InstallPanel::class => fn(): InstallPanel => new InstallPanel($this->app->config),
			Titles::class => fn(): Titles => $this->resolve(Titles::class),
		]);
	}

	public function add(
		array|object|string $commands,
		string $description = '',
		?Closure $command = null,
	): self {
		if (is_string($commands)) {
			$this->commands->add([$commands => fn(): object => $this->resolve($commands)]);

			return $this;
		}

		if (is_array($commands)) {
			$commands = $this->withAutowiredClasses($commands);
		}

		$this->commands->add($commands, $description, $command);

		return $this;
	}

	/**
	 * Registers the builtin and FrankenPHP dev servers.
	 *
	 * The commands are only registered when the optional celema/server
	 * package is installed, so production installs without dev
	 * requirements skip them.
	 *
	 * @param list<string>|string|null $watch
	 */
	public function server(
		int $port = 1983,
		array|string|null $watch = null,
		string $routePrefix = '',
	): self {
		if (!class_exists(Server::class)) {
			return $this;
		}

		$watch ??= Setup::DEFAULT_WATCH;
		$public = $this->app->config->path->public;

		$this->commands->add([
			Server::class => static fn(): Server => new Server($public, $port, $routePrefix, $watch),
			FrankenPhp::class => static fn(): FrankenPhp => new FrankenPhp(
				$public,
				$port,
				$routePrefix,
				$watch,
			),
		]);

		return $this;
	}

	/**
	 * Registers `i18n:sync` and `i18n:status` for one translation domain.
	 *
	 * The domain scans the given source directories (relative paths resolve
	 * from the app root) plus the app's schema labels, and claims bare
	 * `__()` calls as the default domain. Call once per domain for apps
	 * with several catalogs.
	 *
	 * @param list<string> $locales
	 * @param list<string> $scan
	 */
	public function i18n(
		string $name,
		array $locales,
		array $scan = ['src', 'views'],
		string $dir = 'lang',
		bool $schema = true,
	): self {
		$root = $this->app->config->path->root;
		$absolute = static fn(string $path): string => str_starts_with($path, '/')
			? $path
			: "{$root}/{$path}";

		$scanners = [new PhpScanner(array_map($absolute, $scan))];

		if ($schema) {
			$scanners[] = SchemaScanner::fromApp($this->app);
		}

		$domain = new Domain(
			name: $name,
			dir: $absolute($dir),
			locales: $locales,
			scanners: $scanners,
			default: true,
		);

		$this->commands->add([
			SyncCommand::class => static fn(): SyncCommand => new SyncCommand([$domain]),
			StatusCommand::class => static fn(): StatusCommand => new StatusCommand([$domain]),
		]);

		return $this;
	}

	public function runner(?bool $debug = null): Runner
	{
		return new Runner($this->commands, debug: $debug ?? $this->app->config->debug());
	}

	public function commands(): BaseCommands
	{
		return $this->commands;
	}

	private function conn(): Connection
	{
		$conn = $this->container()->get(Connection::class);
		assert($conn instanceof Connection, 'The database connection must be available');

		return $conn;
	}

	private function container(): Container
	{
		return $this->app->container();
	}

	/** @param class-string $class */
	private function resolve(string $class): object
	{
		return ($this->runtime ??= new Runtime($this->app))->get($class);
	}

	private function withAutowiredClasses(array $commands): array
	{
		$result = [];

		foreach ($commands as $key => $command) {
			if (is_int($key) && is_string($command)) {
				$result[$command] = fn(): object => $this->resolve($command);

				continue;
			}

			$result[$key] = $command;
		}

		return $result;
	}
}
