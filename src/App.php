<?php

declare(strict_types=1);

namespace Cosray;

use BadMethodCallException;
use Celema\Container\Container;
use Celema\Container\Entry;
use Celema\Core\App as CoreApp;
use Celema\Core\Factory\Factory;
use Celema\Core\Factory\Nyholm;
use Celema\Core\Plugin as CorePlugin;
use Celema\Router\AddsRoutes;
use Celema\Router\After;
use Celema\Router\Before;
use Celema\Router\Group;
use Celema\Router\Route;
use Celema\Router\RouteAdder;
use Celema\Router\Router;
use Closure;
use Cosray\Node\Types;
use Cosray\View\Boiler\Error\Handler as ErrorHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Log\LoggerInterface as Logger;

/**
 * Convenience facade for CMS applications.
 */
class App implements RouteAdder
{
	use AddsRoutes;

	protected readonly CoreApp $core;
	protected readonly Bootstrap $bootstrap;
	protected bool $booted = false;

	public function __construct(
		public readonly Config $config,
		Factory $factory,
		Router $router,
		Container $container,
	) {
		$this->core = new CoreApp($factory, $router, $container);
		$this->bootstrap = new Bootstrap($config);
		$this->addErrorHandler($container, $factory);
	}

	protected function addErrorHandler(Container $container, Factory $factory): void
	{
		if ($this->config->error->enabled) {
			$this->core->errorHandler(
				new ErrorHandler($this->config, $factory, new ContainerLogger($container))->create(),
			);
		}
	}

	/** @param array<string, mixed> $settings */
	public static function create(string $root, array $settings = []): self
	{
		$config = new Config($root, $settings);

		return new self(
			$config,
			new Nyholm(),
			new Router($config->path->prefix),
			new Container(),
		);
	}

	public function boot(): self
	{
		if (!$this->booted) {
			$this->core->load($this->bootstrap);
			$this->core->addRoute($this->bootstrap->catchallRoute());
			$this->booted = true;
		}

		return $this;
	}

	public function run(?Request $request = null): Response|false
	{
		$this->boot();

		return $this->core->run($request);
	}

	public function config(): Config
	{
		return $this->config;
	}

	public function core(): CoreApp
	{
		return $this->core;
	}

	public function bootstrap(): Bootstrap
	{
		return $this->bootstrap;
	}

	/** @param class-string<Plugin\Plugin>|Plugin\Plugin $plugin */
	public function plugin(string|Plugin\Plugin $plugin): self
	{
		$this->bootstrap->plugin($plugin);

		return $this;
	}

	public function section(string $name): Section
	{
		return $this->bootstrap->section($name);
	}

	/** @param class-string<Collection> $class */
	public function collection(string $class): Collection\Ref
	{
		return $this->bootstrap->collection($class);
	}

	/** @param class-string<Contract\Icons>|Contract\Icons $icons */
	public function icons(string|Contract\Icons $icons, bool $replace = false): self
	{
		$this->bootstrap->icons($icons, $replace);

		return $this;
	}

	public function navigation(): Navigation
	{
		return $this->bootstrap->navigation();
	}

	public function meta(): Types
	{
		return $this->bootstrap->meta();
	}

	/** @param class-string $class */
	public function node(string $class): self
	{
		$this->bootstrap->node($class);

		return $this;
	}

	/** @param class-string<Renderer> $class */
	public function renderer(string $id, string $class): Entry
	{
		return $this->bootstrap->renderer($id, $class);
	}

	public function load(CorePlugin $plugin): self
	{
		$this->core->load($plugin);

		return $this;
	}

	/** @param Closure(Router $router): void $creator */
	public function routes(Closure $creator, string $cacheFile = '', bool $shouldCache = true): self
	{
		$this->core->routes($creator, $cacheFile, $shouldCache);

		return $this;
	}

	public function addRoute(Route $route): Route
	{
		return $this->core->addRoute($route);
	}

	public function addGroup(Group $group): void
	{
		$group->register($this->core);
	}

	public function group(
		string $patternPrefix,
		Closure $createClosure,
		string $namePrefix = '',
	): void {
		$this->core->group($patternPrefix, $createClosure, $namePrefix);
	}

	public function staticRoute(string $prefix, string $path, string $name = ''): self
	{
		$this->core->staticRoute($prefix, $path, $name);

		return $this;
	}

	public function before(Before $beforeHandler): self
	{
		$this->core->before($beforeHandler);

		return $this;
	}

	/** @return list<Before> */
	public function beforeHandlers(): array
	{
		return $this->core->beforeHandlers();
	}

	public function after(After $afterHandler): self
	{
		$this->core->after($afterHandler);

		return $this;
	}

	/** @return list<After> */
	public function afterHandlers(): array
	{
		return $this->core->afterHandlers();
	}

	/** @return list<Middleware> */
	public function getMiddleware(): array
	{
		return $this->core->getMiddleware();
	}

	public function middleware(Middleware ...$middleware): self
	{
		$this->core->middleware(...$middleware);

		return $this;
	}

	public function logger(Logger|callable $logger): self
	{
		$this->core->logger($logger);

		return $this;
	}

	public function container(): Container
	{
		return $this->core->container();
	}

	public function factory(): Factory
	{
		return $this->core->factory();
	}

	public function router(): Router
	{
		return $this->core->router();
	}

	/**
	 * @param non-empty-string $key
	 * @param class-string|object $value
	 */
	public function register(string $key, object|string $value): Entry
	{
		return $this->core->register($key, $value);
	}

	/** @param list<mixed> $args */
	public function __call(string $method, array $args): mixed
	{
		$coreCallable = is_callable([$this->core, $method]);
		$bootstrapCallable = is_callable([$this->bootstrap, $method]);

		if ($coreCallable && $bootstrapCallable) {
			throw new BadMethodCallException("Ambiguous CMS app method: {$method}");
		}

		if ($bootstrapCallable) {
			return $this->bootstrap->{$method}(...$args);
		}

		if ($coreCallable) {
			return $this->core->{$method}(...$args);
		}

		throw new BadMethodCallException("Unknown CMS app method: {$method}");
	}
}
