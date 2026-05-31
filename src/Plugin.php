<?php

declare(strict_types=1);

namespace Cosray;

use Celemas\Container\Container;
use Celemas\Container\Entry;
use Celemas\Core\App;
use Celemas\Core\Factory\Factory;
use Celemas\Core\Plugin as CorePlugin;
use Celemas\Core\Request;
use Celemas\Quma\Connection;
use Celemas\Quma\Database;
use Celemas\Quma\Delimiters;
use Celemas\Router\Route;
use Cosray\Boiler\Renderer as BoilerRenderer;
use Cosray\Exception\RuntimeException;
use Cosray\Icons\Iconify;
use Cosray\Icons\Local;
use Cosray\Node\Node;
use Cosray\Node\Types;
use PDO;

class Plugin implements CorePlugin
{
	public const string NODE_TAG = 'cosray.cms.node';

	protected readonly Factory $factory;
	protected readonly Container $container;
	protected readonly Database $db;
	protected readonly Connection $connection;
	protected readonly Routes $routes;
	protected readonly Types $types;

	/** @property array<Entry> */
	protected array $renderers = [];

	protected readonly Navigation $navigation;
	protected array $nodes = [];

	/** @var list<class-string<Contract\Icons>|Contract\Icons> */
	protected array $customIconProviders = [];
	protected bool $replaceDefaultIconProviders = false;

	public function __construct(
		protected readonly Config $config,
		?Types $types = null,
	) {
		$this->types = $types ?? new Types();
		$this->navigation = new Navigation();
	}

	public function load(App $app): void
	{
		$this->factory = $app->factory();
		$this->container = $app->container();

		$this->addPanelRenderer();
		$this->addViewRenderer();

		$this->collect();
		$this->database();

		$this->container->add($this->container::class, $this->container);
		$this->container->add(Config::class, $this->config);
		$this->container->add($this->config::class, $this->config);
		$this->container->add(Connection::class, $this->connection);
		$this->container->add(Database::class, $this->db);
		$this->container->add(Factory::class, $this->factory);
		$this->container->add(Types::class, $this->types);
		$this->container->add(Contract\Icons::class, Icons::class);

		$this->routes = new Routes($this->config, $this->db, $this->factory);
		$this->routes->add($app);
	}

	protected function collect(): void
	{
		$this->container->add(Navigation::class, $this->navigation)->value();

		foreach ($this->navigation->refs() as $name => $collection) {
			$this->container
				->tag(Collection::class)
				->add($name, $collection::class);
		}

		foreach ($this->nodes as $name => $node) {
			$this->container
				->tag(self::NODE_TAG)
				->add($name, $node);
		}

		foreach ($this->renderers as $entry) {
			$this->container
				->tag(Renderer::class)
				->addEntry($entry);
		}

		$providers = $this->customIconProviders;

		if (!$this->replaceDefaultIconProviders) {
			$providers[] = new Local($this->localIconPaths());
			$providers[] = Iconify::class;
		}

		foreach ($providers as $index => $provider) {
			$this->container
				->tag(Contract\Icons::class)
				->add(sprintf('icons.%d', $index), $provider);
		}
	}

	public function section(string $name): Section
	{
		return $this->navigation->section($name);
	}

	/** @param class-string<Collection> $class */
	public function collection(string $class): Collection
	{
		return $this->navigation->collection($class);
	}

	/**
	 * @param class-string<Contract\Icons>|Contract\Icons $icons
	 */
	public function icons(string|Contract\Icons $icons, bool $replace = false): void
	{
		if (is_string($icons) && !is_a($icons, Contract\Icons::class, true)) {
			throw new RuntimeException('Icons providers must implement ' . Contract\Icons::class);
		}

		if ($replace) {
			$this->customIconProviders = [];
			$this->replaceDefaultIconProviders = true;
		}

		array_unshift($this->customIconProviders, $icons);
	}

	public function navigation(): Navigation
	{
		return $this->navigation;
	}

	public function meta(): Types
	{
		return $this->types;
	}

	public function node(string $class): void
	{
		$handle = (string) $this->types->get($class, 'handle');

		if (isset($this->nodes[$handle])) {
			throw new RuntimeException('Duplicate node handle: ' . $handle);
		}

		$this->nodes[$handle] = $class;
	}

	/** @return list<string> */
	protected function localIconPaths(): array
	{
		return $this->config->icons->localPaths;
	}

	protected function database(): void
	{
		$root = dirname(__DIR__);
		$config = $this->config->db;
		$sql = array_merge(
			[$root . '/db/sql'],
			$config->sql,
		);
		$migrationPaths = $config->migrations;

		$namespacedMigrations = [];
		$namespacedMigrations['install'] = [$root . '/db/migrations/install'];
		$namespacedMigrations['default'] = array_merge(
			$migrationPaths,
			[$root . '/db/migrations/update'],
		);

		$this->connection = new Connection(
			$config->dsn,
			$sql,
		)
			->migrations($namespacedMigrations)
			->fetch(PDO::FETCH_ASSOC)
			->options($config->options)
			->placeholders(Delimiters::comments(), $config->placeholders);
		$this->db = new Database($this->connection);
	}

	/**
	 * Catchall for page url paths.
	 *
	 * Should be the last one
	 */
	public function catchallRoute(): Route
	{
		return $this->routes->catchallRoute();
	}

	public function renderer(string $id, string $class): Entry
	{
		if (is_a($class, Renderer::class, true)) {
			$entry = new Entry($id, $class);
			$this->renderers[] = $entry;

			return $entry;
		}

		throw new RuntimeException('Renderers must imlement the `Cosray\\Renderer` interface');
	}

	protected function synchronizeNodes(): void
	{
		if (!$this->db->sys->isInitialized()->one()['value']) {
			return;
		}

		$types = array_map(
			static fn($record) => $record['handle'],
			$this->db
				->nodes
				->types()
				->all(),
		);

		foreach ($this->nodes as $handle => $class) {
			if (in_array($handle, $types, true)) {
				continue;
			}

			$this->db->nodes->addType([
				'handle' => $handle,
			])->run();
		}
	}

	protected function addPanelRenderer(): void
	{
		$root = dirname(__DIR__);
		$this->renderer('panel', BoilerRenderer::class)->args(
			dirs: "{$root}/panel/views",
			autoescape: true,
		);
	}

	protected function addViewRenderer(): void
	{
		if ($this->hasRenderer('view')) {
			return;
		}

		$this->renderer('view', BoilerRenderer::class)->args(
			dirs: $this->viewPath(),
			autoescape: true,
			trusted: $this->trustedViewClasses(),
		);
	}

	protected function hasRenderer(string $id): bool
	{
		foreach ($this->renderers as $entry) {
			if ($entry->id === $id) {
				return true;
			}
		}

		return false;
	}

	protected function viewPath(): string
	{
		$path = $this->config->path;

		return rtrim($path->root, '/') . '/' . ltrim($path->views, '/');
	}

	/** @return list<class-string> */
	protected function trustedViewClasses(): array
	{
		return [
			Node::class,
			Cms::class,
			Locales::class,
			Locale::class,
			Config::class,
			Request::class,
		];
	}
}
