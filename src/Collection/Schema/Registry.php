<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

use Cosray\CollectionListMeta;
use Cosray\Schema\Badge;
use Cosray\Schema\Blueprints;
use Cosray\Schema\Handle;
use Cosray\Schema\Hidden;
use Cosray\Schema\Icon;
use Cosray\Schema\Label;
use Cosray\Schema\Listing;
use Cosray\Schema\Order;
use Cosray\Schema\Permission;

class Registry
{
	/** @var array<class-string, Handler> */
	private array $handlers = [];

	/** @var array<string, callable(class-string, array<string, mixed>): mixed> */
	private array $defaults = [];

	public function __construct()
	{
		$this->registerDefaultProperties();
	}

	/** @param class-string $schema */
	public function register(string $schema, Handler $handler): void
	{
		$this->handlers[$schema] = $handler;
	}

	public function getHandler(object $schema): ?Handler
	{
		return $this->handlers[$schema::class] ?? null;
	}

	/**
	 * @param callable(class-string, array<string, mixed>): mixed $resolver
	 */
	public function default(string $key, callable $resolver): void
	{
		$this->defaults[$key] = $resolver;
	}

	/**
	 * @param class-string $class
	 * @param array<string, mixed> $resolved
	 * @return array<string, mixed>
	 */
	public function resolveDefaults(string $class, array $resolved): array
	{
		$properties = $resolved;

		foreach ($this->defaults as $key => $resolver) {
			if (array_key_exists($key, $properties)) {
				continue;
			}

			$properties[$key] = $resolver($class, $properties);
		}

		return $properties;
	}

	public static function withDefaults(): self
	{
		$registry = new self();
		$registry->register(Handle::class, new HandleHandler());
		$registry->register(Label::class, new LabelHandler());
		$registry->register(Icon::class, new IconHandler());
		$registry->register(Badge::class, new BadgeHandler());
		$registry->register(Permission::class, new PermissionHandler());
		$registry->register(Hidden::class, new HiddenHandler());
		$registry->register(Order::class, new OrderHandler());
		$registry->register(Listing::class, new ListingHandler());
		$registry->register(Blueprints::class, new BlueprintsHandler());

		return $registry;
	}

	private function registerDefaultProperties(): void
	{
		$this->default('handle', static fn(
			string $class,
			array $properties,
		): string => self::deriveHandle(self::className($class)));
		$this->default(
			'label',
			static fn(
				string $class,
				array $properties,
			): string => (string) preg_replace('/(?<!^)[A-Z]/', ' $0', self::className($class)),
		);
		$this->default('icon', static fn(string $class, array $properties): ?array => null);
		$this->default('badge', static fn(string $class, array $properties): ?string => null);
		$this->default('permission', static fn(string $class, array $properties): ?string => null);
		$this->default('hidden', static fn(string $class, array $properties): bool => false);
		$this->default('order', static fn(string $class, array $properties): int => 0);
		$this->default(
			'listing',
			static fn(string $class, array $properties): CollectionListMeta => new CollectionListMeta(),
		);
		$this->default('blueprints', static fn(string $class, array $properties): array => []);
	}

	/**
	 * @param class-string $class
	 */
	private static function className(string $class): string
	{
		return basename(str_replace('\\', '/', $class));
	}

	private static function deriveHandle(string $className): string
	{
		return ltrim(
			strtolower(preg_replace(
				'/[A-Z]([A-Z](?![a-z]))*/',
				'-$0',
				$className,
			)),
			'-',
		);
	}
}
