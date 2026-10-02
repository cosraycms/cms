<?php

declare(strict_types=1);

namespace Cosray\Block;

use Closure;
use Cosray\Contract\Block;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Owner;

/**
 * The default offer list of block types — what a Blocks field without
 * `#[Allows]` offers — and the factory building type instances for a
 * render.
 */
final class Registry
{
	/** @var list<class-string<Block>> */
	private array $types = [];

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

	/**
	 * A fresh instance built for the owner's request, like an embedded
	 * class: block types are node-local helpers, never container services,
	 * and never receive a node. The constructor can ask for the Owner and
	 * the types Context::create() provides.
	 *
	 * @param class-string<Block> $class
	 */
	public function create(string $class, Owner $owner): Block
	{
		self::assertBlock($class);

		return $owner->create($class, [Owner::class => $owner]);
	}

	/**
	 * The factory for one render: each type is created once and reused
	 * for every block of that type.
	 *
	 * @return Closure(class-string<Block>): Block
	 */
	public function cached(Owner $owner): Closure
	{
		$instances = [];

		return function (string $class) use ($owner, &$instances): Block {
			return $instances[$class] ??= $this->create($class, $owner);
		};
	}

	public static function withDefaults(): self
	{
		$registry = new self();
		$registry->register(RichText::class);
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
