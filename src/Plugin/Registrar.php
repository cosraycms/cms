<?php

declare(strict_types=1);

namespace Cosray\Plugin;

use Celemas\Container\Entry;
use Closure;
use Cosray\Block\Type as BlockType;
use Cosray\Bootstrap;
use Cosray\Collection;
use Cosray\Collection\Ref;
use Cosray\Collection\Schema\Handler as CollectionHandler;
use Cosray\Config;
use Cosray\Field\Schema\Handler as FieldHandler;
use Cosray\Node\Schema\Handler as NodeHandler;
use Cosray\Section;

/**
 * Registration facade handed to each plugin during boot.
 */
final class Registrar
{
	public function __construct(
		private readonly Bootstrap $bootstrap,
		public readonly string $id,
		public readonly Config $config,
	) {}

	/** @param class-string<\Cosray\Field\Field> $class */
	public function field(string $class, string ...$aliases): void
	{
		$this->bootstrap->fields()->add($class, ...$aliases);
	}

	/** @param class-string $attribute */
	public function fieldSchema(string $attribute, FieldHandler $handler): void
	{
		$this->bootstrap->fieldSchemas()->register($attribute, $handler);
	}

	/** @param class-string $attribute */
	public function nodeSchema(string $attribute, NodeHandler $handler): void
	{
		$this->bootstrap->nodeSchemas()->register($attribute, $handler);
	}

	/** @param class-string $attribute */
	public function collectionSchema(string $attribute, CollectionHandler $handler): void
	{
		$this->bootstrap->collectionSchemas()->registry()->register($attribute, $handler);
	}

	/** @param class-string $class */
	public function node(string $class): void
	{
		$this->bootstrap->node($class);
	}

	public function section(string $name): Section
	{
		return $this->bootstrap->section($name);
	}

	/** @param class-string<Collection> $class */
	public function collection(string $class): Ref
	{
		return $this->bootstrap->collection($class);
	}

	public function migrations(string $dir): void
	{
		$this->bootstrap->addMigrations($dir);
	}

	public function sql(string $dir): void
	{
		$this->bootstrap->addSql($dir);
	}

	/**
	 * Serve prebuilt plugin assets from $dir under
	 * `{panel}/vendor/{pluginId}/...`.
	 */
	public function assets(string $dir): void
	{
		$this->bootstrap->addAssets($this->id, $dir);
	}

	/** @param class-string<BlockType>|BlockType $type */
	public function blockType(string|BlockType $type): void
	{
		$this->bootstrap->blockType(is_string($type) ? new $type() : $type);
	}

	/**
	 * Register $dir as template namespace `{pluginId}:` for the given
	 * renderer ('panel' or 'view'). Templates are addressed as
	 * '{pluginId}:template/path'.
	 */
	public function templates(string $dir, string $renderer = 'panel'): void
	{
		$this->bootstrap->addTemplates($this->id, $dir, $renderer);
	}

	/**
	 * Register a page inside the panel chrome: session, auth and the
	 * panel renderer are applied like for built-in pages. The endpoint
	 * controller should extend Cosray\Controller\Panel\Panel and
	 * return $this->context([...]) so the shell gets its data.
	 *
	 * @param mixed $endpoint route endpoint, e.g. [Controller::class, 'method']
	 */
	public function panelPage(string $pattern, mixed $endpoint, string $template, string $name): void
	{
		$this->bootstrap->addPanelPage($pattern, $endpoint, $template, "{$this->id}.{$name}");
	}

	public function css(string $url): void
	{
		$this->bootstrap->panelExtras()->addCss($url);
	}

	public function js(string $url, bool $module = true): void
	{
		$this->bootstrap->panelExtras()->addJs($url, $module);
	}

	/**
	 * @param non-empty-string $key
	 * @param class-string|object $value
	 */
	public function register(string $key, object|string $value): Entry
	{
		return $this->bootstrap->addService($key, $value);
	}

	/** @param Closure(\Celemas\Core\App): void $routes */
	public function routes(Closure $routes): void
	{
		$this->bootstrap->addRoutes($routes);
	}
}
