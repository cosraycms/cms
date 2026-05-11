<?php

declare(strict_types=1);

namespace Celemas\Cms\Node\Schema;

use Celemas\Cms\Schema\Children;
use Celemas\Cms\Schema\Deletable;
use Celemas\Cms\Schema\FieldOrder;
use Celemas\Cms\Schema\Handle;
use Celemas\Cms\Schema\Icon;
use Celemas\Cms\Schema\Label;
use Celemas\Cms\Schema\Permission;
use Celemas\Cms\Schema\Render;
use Celemas\Cms\Schema\Route;
use Celemas\Cms\Schema\Title;

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
	 * @param class-string $nodeClass
	 * @param array<string, mixed> $resolved
	 * @return array<string, mixed>
	 */
	public function resolveDefaults(string $nodeClass, array $resolved): array
	{
		$properties = $resolved;

		foreach ($this->defaults as $key => $resolver) {
			if (array_key_exists($key, $properties)) {
				continue;
			}

			$properties[$key] = $resolver($nodeClass, $properties);
		}

		return $properties;
	}

	/** @return list<string> */
	public function defaultKeys(): array
	{
		return array_keys($this->defaults);
	}

	public static function withDefaults(): self
	{
		$registry = new self();
		$registry->register(Handle::class, new HandleHandler());
		$registry->register(Label::class, new LabelHandler());
		$registry->register(Icon::class, new IconHandler());
		$registry->register(Route::class, new RouteHandler());
		$registry->register(Render::class, new RenderHandler());
		$registry->register(Permission::class, new PermissionHandler());
		$registry->register(Title::class, new TitleHandler());
		$registry->register(FieldOrder::class, new FieldOrderHandler());
		$registry->register(Deletable::class, new DeletableHandler());
		$registry->register(Children::class, new ChildrenHandler());

		return $registry;
	}

	private function registerDefaultProperties(): void
	{
		$this->default('handle', static fn(
			string $nodeClass,
			array $properties,
		): string => self::deriveHandle(self::className($nodeClass)));
		$this->default('label', static fn(
			string $nodeClass,
			array $properties,
		): string => self::className($nodeClass));
		$this->default('icon', static fn(string $nodeClass, array $properties): ?array => null);
		$this->default(
			'renderer',
			static fn(string $nodeClass, array $properties): string => (string) (
				$properties['handle'] ?? ''
			),
		);
		$this->default('route', static fn(string $nodeClass, array $properties): string => '');
		$this->default('routable', static fn(string $nodeClass, array $properties): bool => false);
		$this->default(
			'renderable',
			static fn(string $nodeClass, array $properties): bool => $properties['routable'] ?? false,
		);
		$this->default('permission', static fn(string $nodeClass, array $properties): array => [
			'read' => 'everyone',
			'create' => 'authenticated',
			'change' => 'authenticated',
			'delete' => 'authenticated',
		]);
		$this->default('titleField', static fn(string $nodeClass, array $properties): ?string => null);
		$this->default('fieldOrder', static fn(string $nodeClass, array $properties): ?array => null);
		$this->default('deletable', static fn(string $nodeClass, array $properties): bool => true);
		$this->default('children', static fn(string $nodeClass, array $properties): array => []);
	}

	/**
	 * @param class-string $nodeClass
	 */
	private static function className(string $nodeClass): string
	{
		return basename(str_replace('\\', '/', $nodeClass));
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
