<?php

declare(strict_types=1);

namespace Cosray\Block;

use Celema\Wire\Creator;
use Cosray\Config;
use Cosray\Contract\Block;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Owner;
use Psr\Container\ContainerInterface as Container;

/**
 * The default offer list of block types — what a Blocks field without
 * `#[Allows]` offers — and the factory building type instances for a
 * render.
 */
final class Registry
{
	/** @var list<class-string<Block>> */
	private array $types = [];

	private ?Container $container = null;

	/** @param class-string<Block> $class */
	public function register(string $class): void
	{
		self::assertBlock($class);

		if (!in_array($class, $this->types, true)) {
			$this->types[] = $class;
		}
	}

	/** @param class-string $class */
	public function has(string $class): bool
	{
		return in_array($class, $this->types, true);
	}

	/** @return list<class-string<Block>> */
	public function all(): array
	{
		return $this->types;
	}

	/** Block type constructors are autowired from this container. */
	public function useContainer(Container $container): void
	{
		$this->container = $container;
	}

	/**
	 * A fresh instance with autowired constructor arguments, like an
	 * embedded class: block types are node-local helpers, never container
	 * services, and never receive a node.
	 *
	 * @param class-string<Block> $class
	 */
	public function create(string $class, Owner $owner): Block
	{
		self::assertBlock($class);

		if ($this->container?->has($class)) {
			throw new RuntimeException("Block type '{$class}' must not be registered as a container service.");
		}

		$instance = new Creator($this->container)->create($class, predefinedTypes: [
			Owner::class => $owner,
			Config::class => $owner->config(),
		]);
		assert($instance instanceof Block, 'The creator returns the requested class');

		return $instance;
	}

	public static function withDefaults(): self
	{
		$registry = new self();
		$registry->register(RichText::class);
		$registry->register(Text::class);
		$registry->register(Heading::class);
		$registry->register(Image::class);
		$registry->register(Images::class);
		$registry->register(Video::class);
		$registry->register(Youtube::class);
		$registry->register(Iframe::class);

		return $registry;
	}

	private static function assertBlock(string $class): void
	{
		if (!class_exists($class) || !is_a($class, Block::class, true)) {
			throw new RuntimeException('Block types must implement ' . Block::class . ": {$class}");
		}
	}
}
