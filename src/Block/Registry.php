<?php

declare(strict_types=1);

namespace Cosray\Block;

use Cosray\Exception\RuntimeException;

final class Registry
{
	/** @var array<string, Type> */
	private array $types = [];

	public function register(Type $type): void
	{
		$this->types[$type->id()] = $type;
	}

	public function has(string $id): bool
	{
		return isset($this->types[$id]);
	}

	public function get(string $id): Type
	{
		return (
			$this->types[$id] ?? throw new RuntimeException(
				"Unknown block type '{$id}'. Register it via Registrar::blockType() if it comes from a plugin.",
			)
		);
	}

	/** @return list<Type> */
	public function all(): array
	{
		return array_values($this->types);
	}

	public static function withDefaults(): self
	{
		$registry = new self();
		$registry->register(new Types\RichText());
		$registry->register(new Types\Text());
		$registry->register(new Types\Image());
		$registry->register(new Types\Youtube());
		$registry->register(new Types\Images());
		$registry->register(new Types\Video());
		$registry->register(new Types\Iframe());

		foreach (range(1, 6) as $level) {
			$registry->register(new Types\Heading($level));
		}

		return $registry;
	}
}
