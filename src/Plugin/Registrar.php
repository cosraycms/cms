<?php

declare(strict_types=1);

namespace Cosray\Plugin;

use Celemas\Container\Entry;
use Closure;
use Cosray\Bootstrap;
use Cosray\Collection;
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
	public function collection(string $class): Collection
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
