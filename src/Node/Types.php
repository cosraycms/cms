<?php

declare(strict_types=1);

namespace Celemas\Cms\Node;

use Celemas\Cms\Node\Schema\Registry;

class Types
{
	/** @var array<class-string, Schema> */
	private array $cache = [];

	private readonly Registry $registry;

	public function __construct(?Registry $registry = null)
	{
		$this->registry = $registry ?? Registry::withDefaults();
	}

	/**
	 * @param class-string $class
	 */
	public function schemaOf(string $class): Schema
	{
		return $this->cache[$class] ??= new Schema($class, $this->registry);
	}

	/**
	 * @param class-string $class
	 */
	public function typeOf(string $class): Type
	{
		return new Type($class, $this);
	}

	/**
	 * @param class-string $class
	 */
	public function get(string $class, string $key, mixed $default = null): mixed
	{
		return $this->schemaOf($class)->get($key, $default);
	}

	/**
	 * @param class-string $class
	 */
	public function isNode(string $class): bool
	{
		return class_exists($class);
	}

	public function clearCache(): void
	{
		$this->cache = [];
	}
}
