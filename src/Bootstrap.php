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
use Closure;
use Cosray\Block\Registry as BlockRegistry;
use Cosray\Block\Type as BlockType;
use Cosray\Collection\Ref as CollectionRef;
use Cosray\Collection\Schema\Registry as CollectionSchemaRegistry;
use Cosray\Collection\Schemas as CollectionSchemas;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Index as FieldIndex;
use Cosray\Field\Schema\Registry as FieldSchemas;
use Cosray\Field\Services as FieldServices;
use Cosray\Icons\Iconify;
use Cosray\Icons\Local;
use Cosray\Node\Node;
use Cosray\Node\Schema\Registry as NodeSchemas;
use Cosray\Node\Types;
use Cosray\Panel\CollectionPage;
use Cosray\Panel\CollectionQuery;
use Cosray\Panel\CollectionUrls;
use Cosray\Panel\Extras as PanelExtras;
use Cosray\Plugin\Assets as PluginAssets;
use Cosray\Plugin\Plugin;
use Cosray\Plugin\Registrar;
use Cosray\View\Boiler\Renderer as BoilerRenderer;
use PDO;

class Bootstrap implements CorePlugin
{
	public const string NODE_TAG = 'cosray.cms.node';

	protected readonly Factory $factory;
	protected readonly Container $container;
	protected readonly Database $db;
	protected readonly Connection $connection;
	protected readonly Routes $routes;
	protected readonly Types $types;
	protected readonly FieldSchemas $fieldSchemas;
	protected readonly FieldServices $fieldServices;
	protected readonly FieldIndex $fields;
	protected readonly BlockRegistry $blocks;
	protected readonly CollectionSchemas $collectionSchemas;

	/** @property array<Entry> */
	protected array $renderers = [];

	protected readonly Navigation $navigation;
	protected array $nodes = [];

	/** @var list<class-string<Contract\Icons>|Contract\Icons> */
	protected array $customIconProviders = [];
	protected bool $replaceDefaultIconProviders = false;

	/** @var list<class-string<Plugin>|Plugin> */
	protected array $plugins = [];

	/** @var array<string, true> */
	protected array $pluginIds = [];

	/** @var list<string> */
	protected array $pluginMigrations = [];

	/** @var list<string> */
	protected array $pluginSql = [];

	/** @var list<Closure> */
	protected array $pluginRoutes = [];

	protected readonly PluginAssets $pluginAssets;
	protected readonly PanelExtras $panelExtras;

	/** @var array<string, array<string, string>> */
	protected array $pluginTemplates = [
		'panel' => [],
		'view' => [],
	];

	/** @var list<array{pattern: string, endpoint: mixed, template: string, name: string}> */
	protected array $panelPages = [];

	public function __construct(
		protected readonly Config $config,
		?Types $types = null,
	) {
		$this->types = $types ?? new Types();
		$this->fieldSchemas = FieldSchemas::withDefaults();
		$this->blocks = BlockRegistry::withDefaults();
		$this->fieldServices = new FieldServices($this->fieldSchemas, $this->types, $this->blocks);
		$this->fields = FieldIndex::withDefaults();
		$this->pluginAssets = new PluginAssets();
		$this->panelExtras = new PanelExtras();
		$this->collectionSchemas = new CollectionSchemas(CollectionSchemaRegistry::withDefaults());
		$this->navigation = new Navigation($this->collectionSchemas);
	}

	public function load(App $app): void
	{
		$this->factory = $app->factory();
		$this->container = $app->container();

		$this->loadPlugins();

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
		$this->container->add(NodeSchemas::class, $this->types->registry());
		$this->container->add(FieldSchemas::class, $this->fieldSchemas);
		$this->container->add(FieldServices::class, $this->fieldServices);
		$this->container->add(FieldIndex::class, $this->fields);
		$this->container->add(BlockRegistry::class, $this->blocks);
		$this->container->add(CollectionSchemas::class, $this->collectionSchemas);
		$this->container->add(CollectionSchemaRegistry::class, $this->collectionSchemas->registry());
		$this->container->add(PluginAssets::class, $this->pluginAssets);
		$this->container->add(PanelExtras::class, $this->panelExtras);
		$this->container->add(Contract\Icons::class, Icons::class);

		$this->routes = new Routes(
			$this->config,
			$this->db,
			$this->factory,
			$this->pluginRoutes,
			$this->panelPages,
		);
		$this->routes->add($app);
	}

	/** @param class-string<Plugin>|Plugin $plugin */
	public function plugin(string|Plugin $plugin): void
	{
		$this->plugins[] = $plugin;
	}

	protected function loadPlugins(): void
	{
		$configured = (array) $this->config->get('plugins', []);

		foreach ([...$configured, ...$this->plugins] as $plugin) {
			if (is_string($plugin)) {
				if (!is_a($plugin, Plugin::class, true)) {
					throw new RuntimeException('Plugins must implement ' . Plugin::class . ": {$plugin}");
				}

				$plugin = new $plugin();
			}

			if (!$plugin instanceof Plugin) {
				throw new RuntimeException('Plugins must implement ' . Plugin::class);
			}

			$id = $plugin->id();

			if (isset($this->pluginIds[$id])) {
				throw new RuntimeException("Duplicate plugin id: {$id}");
			}

			$this->pluginIds[$id] = true;
			$plugin->register(new Registrar($this, $id, $this->config));
		}
	}

	public function addMigrations(string $dir): void
	{
		$this->pluginMigrations[] = $dir;
	}

	public function addSql(string $dir): void
	{
		$this->pluginSql[] = $dir;
	}

	public function addAssets(string $id, string $dir): void
	{
		$this->pluginAssets->add($id, $dir);
	}

	public function blockType(BlockType $type): void
	{
		$this->blocks->register($type);
	}

	public function addTemplates(string $namespace, string $dir, string $renderer): void
	{
		if (!array_key_exists($renderer, $this->pluginTemplates)) {
			throw new RuntimeException("Unknown template renderer '{$renderer}'. Use 'panel' or 'view'.");
		}

		$this->pluginTemplates[$renderer][$namespace] = $dir;
	}

	public function addPanelPage(
		string $pattern,
		mixed $endpoint,
		string $template,
		string $name,
	): void {
		$this->panelPages[] = [
			'pattern' => $pattern,
			'endpoint' => $endpoint,
			'template' => $template,
			'name' => $name,
		];
	}

	public function panelExtras(): PanelExtras
	{
		return $this->panelExtras;
	}

	/**
	 * @param non-empty-string $key
	 * @param class-string|object $value
	 */
	public function addService(string $key, object|string $value): Entry
	{
		return $this->container->add($key, $value);
	}

	/** @param Closure(App): void $routes */
	public function addRoutes(Closure $routes): void
	{
		$this->pluginRoutes[] = $routes;
	}

	protected function collect(): void
	{
		$this->container->add(Navigation::class, $this->navigation)->value();

		foreach ($this->navigation->refs() as $name => $ref) {
			$this->container
				->tag(Collection::class)
				->add($name, $ref->class);
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
	public function collection(string $class): CollectionRef
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

	public function fieldSchemas(): FieldSchemas
	{
		return $this->fieldSchemas;
	}

	public function nodeSchemas(): NodeSchemas
	{
		return $this->types->registry();
	}

	public function fieldServices(): FieldServices
	{
		return $this->fieldServices;
	}

	public function fields(): FieldIndex
	{
		return $this->fields;
	}

	public function collectionSchemas(): CollectionSchemas
	{
		return $this->collectionSchemas;
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
			$this->pluginSql,
		);
		$migrationPaths = $config->migrations;

		$namespacedMigrations = [];
		$namespacedMigrations['install'] = [$root . '/db/migrations/install'];
		$namespacedMigrations['default'] = array_merge(
			$migrationPaths,
			$this->pluginMigrations,
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
			// The cosray dir must come first: un-namespaced lookups
			// (e.g. the 'panel' layout) search the dirs in order.
			dirs: ['cosray' => "{$root}/panel/views", ...$this->pluginTemplates['panel']],
			autoescape: true,
			trusted: [CollectionPage::class, CollectionQuery::class, CollectionUrls::class],
		);
	}

	protected function addViewRenderer(): void
	{
		if ($this->hasRenderer('view')) {
			return;
		}

		$this->renderer('view', BoilerRenderer::class)->args(
			dirs: ['app' => $this->viewPath(), ...$this->pluginTemplates['view']],
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
