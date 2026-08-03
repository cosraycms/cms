<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Container\Container;
use Celema\Core\Factory\Factory as CoreFactory;
use Celema\Core\Request;
use Celema\Quma\Database;
use Celema\Wire\Creator;
use Cosray\Cms;
use Cosray\Config;
use Cosray\Context;
use Cosray\Contract\Init;
use Cosray\Exception\NoSuchField;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Definitions;
use Cosray\Field\EmbeddedDefinition;
use Cosray\Field\Field;
use Cosray\Field\FieldHydrator;
use Cosray\Field\FieldsetDefinition;
use Cosray\Field\Services;
use Cosray\Uid;
use ReflectionClass;
use ReflectionNamedType;
use WeakMap;

class Factory
{
	/**
	 * @var WeakMap<object, array{
	 *     data: array,
	 *     fields: array<string, Field>,
	 *     embedded: array<string, object>,
	 *     fieldsets: list<FieldsetDefinition>
	 * }>
	 */
	private static WeakMap $nodeState;

	private readonly FieldHydrator $hydrator;
	private readonly Types $types;

	public function __construct(
		private readonly Container $container,
		private readonly Services $services,
		private readonly Uid $uid,
	) {
		$this->hydrator = new FieldHydrator($services);
		$this->types = $services->types;
		self::$nodeState ??= new WeakMap();
	}

	/**
	 * Create a node instance from a class name and raw DB data.
	 *
	 * Uses Wire Creator for autowired construction,
	 * then FieldHydrator for field initialization.
	 */
	public function create(string $class, Context $context, Cms $cms, array $data): object
	{
		$serializer = new Serializer($this->types, $this->uid, $context->assets());
		$store = new Store(
			$context->db,
			new PathManager(),
			$this->types,
			$this->uid,
			factory: $this,
			cms: $cms,
			context: $context,
		);
		$templateRenderer = new ViewRenderer(
			$this->container,
			$context->factory,
			$this->types,
		);

		$creator = new Creator($this->container);
		$type = $this->types->typeOf($class);

		// The node's own view has to know the node, which does not exist yet.
		// A lazy proxy closes over the variable and materializes on first use,
		// by which time the node is built.
		$node = null;
		$view = new ReflectionClass(View::class)->newLazyProxy(
			// By reference: an arrow function would capture the null.
			static function () use (&$node, $templateRenderer, $cms, $context): View {
				return new View($node, $templateRenderer, $cms, $context);
			},
		);

		$predefinedTypes = [
			Context::class => $context,
			Cms::class => $cms,
			Request::class => $context->request,
			Config::class => $context->config,
			Database::class => $context->db,
			Container::class => $context->container,
			CoreFactory::class => $context->factory,
			self::class => $this,
			Type::class => $type,
			ViewRenderer::class => $templateRenderer,
			View::class => $view,
			Serializer::class => $serializer,
			Store::class => $store,
			FieldHydrator::class => $this->hydrator,
			Services::class => $this->services,
		];
		$node = $creator->create($class, predefinedTypes: $predefinedTypes);

		$uid = $data['uid'] ?? $this->uid->generate();
		$data['uid'] = $uid;
		$owner = new FieldOwner($context, $uid);
		$hydration = $this->hydrator->hydrateEmbedded(
			$node,
			$data['content'] ?? [],
			$owner,
			function (EmbeddedDefinition $definition) use ($class, $creator, $predefinedTypes): object {
				$this->assertFreshEmbedded($definition, $class);

				return $creator->create($definition->type, predefinedTypes: $predefinedTypes);
			},
		);

		self::$nodeState[$node] = [
			'data' => $data,
			'fields' => $hydration->fields,
			'embedded' => $hydration->embedded,
			'fieldsets' => Definitions::for($class)->fieldsets(),
		];

		if ($node instanceof Init) {
			$node->init();
		}

		return $node;
	}

	/**
	 * Wrap a node for template-friendly access.
	 */
	public function proxy(
		object $node,
		Request $request,
		?Context $context = null,
		?Cms $cms = null,
	): Wrapper {
		return new Wrapper(
			$node,
			self::fieldNamesFor($node),
			$this->types,
			$request,
			$context,
			$cms,
			$this,
		);
	}

	/**
	 * Create a blueprint (empty) node for admin panel schema generation.
	 */
	public function blueprint(string $class, Context $context, Cms $cms): object
	{
		return $this->create($class, $context, $cms, []);
	}

	/**
	 * Get the raw DB data associated with a node instance.
	 */
	public static function dataFor(object $node): array
	{
		return self::getNodeState($node)['data'] ?? [];
	}

	/** @return array<string, Field> */
	public static function fieldsFor(object $node): array
	{
		return self::getNodeState($node)['fields'] ?? [];
	}

	public static function fieldFor(object $node, string $name): Field
	{
		$field = self::fieldsFor($node)[$name] ?? null;

		if ($field === null) {
			$class = $node::class;
			throw new NoSuchField("Node '{$class}' does not have a hydrated field '{$name}'.");
		}

		return $field;
	}

	/** @return list<string> */
	public static function fieldNamesFor(object $node): array
	{
		return array_keys(self::fieldsFor($node));
	}

	/** @return array<string, object> */
	public static function embedsFor(object $node): array
	{
		return self::getNodeState($node)['embedded'] ?? [];
	}

	public static function embeddedFor(object $node, string $name): ?object
	{
		return self::embedsFor($node)[$name] ?? null;
	}

	/** @return list<FieldsetDefinition> */
	public static function fieldsetsFor(object $node): array
	{
		return self::getNodeState($node)['fieldsets'] ?? [];
	}

	private static function getNodeState(object $node): array
	{
		self::$nodeState ??= new WeakMap();
		$node = Wrapper::unwrap($node);

		return self::$nodeState[$node] ?? [];
	}

	/**
	 * Get a metadata value from the raw DB data for a node instance.
	 */
	public static function meta(object $node, string $key): mixed
	{
		return self::dataFor($node)[$key] ?? null;
	}

	/**
	 * Embedded classes are node-local mutable schema objects, never services.
	 *
	 * @param class-string $nodeClass
	 */
	private function assertFreshEmbedded(EmbeddedDefinition $definition, string $nodeClass): void
	{
		if ($this->container->has($definition->type)) {
			throw new RuntimeException(
				"Embedded type '{$definition->type}' must not be registered as a container service.",
			);
		}

		$constructor = new ReflectionClass($definition->type)->getConstructor();

		foreach ($constructor?->getParameters() ?? [] as $parameter) {
			$type = $parameter->getType();

			if ($type instanceof ReflectionNamedType && $type->getName() === $nodeClass) {
				throw new RuntimeException(
					"Embedded type '{$definition->type}' must not inject its containing node '{$nodeClass}'.",
				);
			}
		}
	}

	public function hydrator(): FieldHydrator
	{
		return $this->hydrator;
	}

	public function uid(): Uid
	{
		return $this->uid;
	}
}
