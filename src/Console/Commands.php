<?php

declare(strict_types=1);

namespace Cosray\Console;

use Celema\Console\Io;
use Celema\Console\Runner;
use Celema\Container\Container;
use Celema\Quma\Commands as QumaCommands;
use Celema\Quma\Connection;
use Celema\Server\FrankenInstall;
use Celema\Server\Reload;
use Celema\Server\Server;
use Celema\Verba\Command\StatusCommand;
use Celema\Verba\Command\SyncCommand;
use Celema\Verba\Tool\Domain;
use Celema\Verba\Tool\PhpScanner;
use Cosray\App;
use Cosray\Commands\Fulltext;
use Cosray\Commands\PanelPublish;
use Cosray\Commands\RecreateSortIndex;
use Cosray\Commands\References;
use Cosray\Commands\Superuser;
use Cosray\Commands\Titles;
use Cosray\I18n\SchemaScanner;
use Cosray\MigrationFactory;
use Cosray\Panel\Client;

/**
 * The base CLI command set of a Cosray application.
 *
 * Boots the app and registers the quma migration commands and Cosray's own
 * commands on a console runner. `server()` and `i18n()` register the
 * per-app dev server and translation commands; application command
 * class-strings are lazily autowired from one request-free console scope.
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
	private readonly Runner $runner;
	private ?Runtime $runtime = null;

	public function __construct(
		private readonly App $app,
		Io $io = new Io(),
	) {
		$app->boot();
		$this->runner = new Runner(
			QumaCommands::get($this->conn(), migrationFactory: new MigrationFactory($app->container())),
			$io,
			debug: $app->config->debug(),
			resolve: $this->resolve(...),
		);
		$this->runner->add([
			Fulltext::class,
			PanelPublish::class => static fn(): PanelPublish => new PanelPublish(
				$app->config,
				new Client($app->config),
			),
			References::class => fn(): References => new References($this->conn()),
			RecreateSortIndex::class,
			Superuser::class => fn(): Superuser => new Superuser($this->conn()),
			Titles::class,
		]);
	}

	/**
	 * Takes the registrations a console Runner takes; class-strings are
	 * autowired in the console scope.
	 */
	public function add(array|object|string $commands): self
	{
		$this->runner->add($commands);

		return $this;
	}

	/**
	 * Registers the development server, `frankenphp:install`, and `reload`.
	 *
	 * The server runs the built-in PHP server or, with `server:
	 * 'frankenphp'` or `php run server frankenphp`, FrankenPHP, pinned to
	 * `version` if given. Companions run alongside, like asset watchers:
	 * each name with a command line or a list of arguments. `reload` serves
	 * live reload on the port the server uses for it by default.
	 *
	 * The commands are only registered when the optional celema/server
	 * package is installed, so production installs without dev
	 * requirements skip them.
	 *
	 * @param list<string>|string|null $watch
	 * @param array<string, list<string>|string> $companions
	 */
	public function server(
		int $port = 1983,
		array|string|null $watch = null,
		string $routePrefix = '',
		string $server = 'builtin',
		?string $version = null,
		array $companions = [],
	): self {
		if (!class_exists(Server::class)) {
			return $this;
		}

		$args = [
			'docroot' => $this->app->config->path->public,
			'port' => $port,
			'routePrefix' => $routePrefix,
			'server' => $server,
			'version' => $version,
			'companions' => $companions,
		];
		$reload = ['port' => self::reloadPort($port), 'companions' => $companions];

		// Without patterns, the server's own default applies.
		if ($watch !== null) {
			$args['watch'] = $watch;
			$reload['watch'] = $watch;
		}

		$this->runner->add([
			Server::class => static fn(): Server => new Server(...$args),
			Reload::class => static fn(): Reload => new Reload(...$reload),
			new FrankenInstall(),
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

		$this->runner->add([
			SyncCommand::class => static fn(): SyncCommand => new SyncCommand([$domain]),
			StatusCommand::class => static fn(): StatusCommand => new StatusCommand([$domain]),
		]);

		return $this;
	}

	public function runner(): Runner
	{
		return $this->runner;
	}

	/**
	 * The server's first choice for its live reload port, so pages keep
	 * loading the script when switching between `server` and `reload`.
	 */
	private static function reloadPort(int $port): int
	{
		foreach ([$port * 10, $port + 10_000] as $candidate) {
			if ($candidate <= 65_535) {
				return $candidate;
			}
		}

		return $port + 1;
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
}
