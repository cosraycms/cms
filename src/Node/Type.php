<?php

declare(strict_types=1);

namespace Celemas\Cms\Node;

use Celemas\Cms\Exception\NoSuchProperty;

class Type
{
	public readonly string $class;
	public readonly string $classname;

	private readonly Schema $schema;

	/**
	 * @param class-string $class
	 */
	public function __construct(
		string $class,
		Types $types,
	) {
		$this->class = $class;
		$this->classname = basename(str_replace('\\', '/', $class));
		$this->schema = $types->schemaOf($class);
	}

	public function __get(string $key): mixed
	{
		if (!$this->has($key)) {
			throw new NoSuchProperty("The node type '{$this->class}' doesn't have the property '{$key}'");
		}

		return $this->get($key);
	}

	public function __isset(string $key): bool
	{
		return $this->has($key) && $this->get($key) !== null;
	}

	public function get(string $key, mixed $default = null): mixed
	{
		return match ($key) {
			'class' => $this->class,
			'classname' => $this->classname,
			default => $this->schema->get($key, $default),
		};
	}

	public function has(string $key): bool
	{
		return match ($key) {
			'class', 'classname' => true,
			default => $this->schema->has($key),
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array
	{
		return array_merge($this->schema->properties(), [
			'class' => $this->class,
			'classname' => $this->classname,
		]);
	}
}
