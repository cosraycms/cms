<?php

declare(strict_types=1);

namespace Cosray\Console;

use Celema\Console\Commands as BaseCommands;
use Celema\Console\Runner;
use Celema\Container\Container;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Core\Server\FrankenPhp;
use Celema\Core\Server\Server;
use Celema\Core\Server\Setup;
use Celema\Quma\Commands as QumaCommands;
use Celema\Quma\Connection;
use Celema\Quma\Database;
use Celema\Verba\Command\StatusCommand;
use Celema\Verba\Command\SyncCommand;
use Celema\Verba\Tool\Domain;
use Celema\Verba\Tool\PhpScanner;
use Closure;
use Cosray\App;
use Cosray\Cms;
use Cosray\Commands\Fulltext;
use Cosray\Commands\InstallPanel;
use Cosray\Commands\RecreateSortIndex;
use Cosray\Commands\References;
use Cosray\Commands\Superuser;
use Cosray\Commands\Titles;
use Cosray\Context;
use Cosray\Field\Services;
use Cosray\I18n\SchemaScanner;
use Cosray\Locales;
use Cosray\MigrationFactory;
use Cosray\Node\Types;

/**
 * The base CLI command set of a Cosray application.
 *
 * Bundles the quma migration commands and Cosray's own commands as lazy
 * factories over the booted app. `server()` and `i18n()` register the
 * per-app dev server and translation commands; `add()` accepts anything
 * `Celema\Console\Commands` accepts.
 *
 *     $commands = new Commands($app);
 *     $commands->server(port: 6913, watch: ['src/**\/*.php']);
 *     $commands->i18n('mysite', locales: ['de', 'en']);
 *
 *     return $commands->runner();
 *
 * @api
 */
final class Commands
{
	private readonly BaseCommands $commands;

	public function __construct(
		private readonly App $app,
	) {
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
			Titles::class => fn(): Titles => $this->titles(),
		]);
	}

	public function add(
		array|object|string $commands,
		string $description = '',
		?Closure $command = null,
	): self {
		$this->commands->add($commands, $description, $command);

		return $this;
	}

	/**
	 * Registers the builtin and FrankenPHP dev servers.
	 *
	 * @param list<string>|string $watch
	 */
	public function server(
		int $port = 1983,
		array|string $watch = Setup::DEFAULT_WATCH,
		string $routePrefix = '',
	): self {
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
		assert($conn instanceof Connection);

		return $conn;
	}

	private function container(): Container
	{
		return $this->app->container();
	}

	/**
	 * Builds the `db:titles` command, which needs the booted app context
	 * (node type registry and locales).
	 */
	private function titles(): Titles
	{
		$container = $this->container();
		$factory = $container->get(Factory::class);
		assert($factory instanceof Factory);
		$db = $container->get(Database::class);
		assert($db instanceof Database);
		$services = $container->get(Services::class);
		assert($services instanceof Services);
		$locales = $container->get(Locales::class);
		assert($locales instanceof Locales);
		$types = $container->get(Types::class);
		assert($types instanceof Types);

		$context = new Context(
			$db,
			new Request($factory->serverRequest()),
			$this->app->config,
			$container,
			$factory,
		);

		return new Titles(
			$this->conn(),
			$context,
			new Cms($context, $services),
			$locales,
			$types,
		);
	}
}
