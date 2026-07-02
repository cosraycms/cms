<?php

declare(strict_types=1);

namespace Cosray\Collection;

use Cosray\Collection\Schema\Registry;
use Cosray\NavMeta;

class Schemas
{
	/** @var array<class-string, Schema> */
	private array $cache = [];

	private readonly Registry $registry;

	public function __construct(?Registry $registry = null)
	{
		$this->registry = $registry ?? Registry::withDefaults();
	}

	public function registry(): Registry
	{
		return $this->registry;
	}

	/**
	 * @param class-string $class
	 */
	public function of(string $class): Schema
	{
		return $this->cache[$class] ??= new Schema($class, $this->registry);
	}

	/**
	 * @param class-string $class
	 */
	public function get(string $class, string $key, mixed $default = null): mixed
	{
		return $this->of($class)->get($key, $default);
	}

	/**
	 * @param class-string $class
	 */
	public function nav(string $class): NavMeta
	{
		$schema = $this->of($class);

		return new NavMeta(
			label: $schema->label,
			icon: $schema->icon,
			badge: $schema->badge,
			permission: $schema->permission,
			hidden: $schema->hidden,
			order: $schema->order,
		);
	}
}
