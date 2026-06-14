<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celemas\Container\Container;
use Celemas\Core\Factory\Factory as CoreFactory;
use Celemas\Core\Request;
use Celemas\Quma\Database;
use Celemas\Wire\Creator;
use Cosray\Cms;
use Cosray\Config;
use Cosray\Context;
use Cosray\Field\FieldHydrator;
use Cosray\Field\Schema\Registry as SchemaRegistry;
use Cosray\Node\Contract\HasInit;
use Cosray\Uid;
use WeakMap;

class Factory
{
	/** @var WeakMap<object, array{data: array, fieldNames: string[]}> */
	private static WeakMap $nodeState;

	private readonly FieldHydrator $hydrator;
	private readonly Types $types;

	public function __construct(
		private readonly Container $container,
		Types $types,
		?SchemaRegistry $schemaRegistry = null,
		private readonly Uid $uid = new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13),
	) {
		$this->hydrator = new FieldHydrator($schemaRegistry ?? SchemaRegistry::withDefaults());
		$this->types = $types;
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
		$serializer = new Serializer($this->hydrator, $this->types, $this->uid);
		$store = new Store($context->db, new PathManager($this->uid), $this->types, $this->uid);
		$templateRenderer = new ViewRenderer(
			$this->container,
			$context->factory,
			$this->hydrator,
			$this->types,
		);

		$creator = new Creator($this->container);
		$type = $this->types->typeOf($class);
		$node = $creator->create($class, predefinedTypes: [
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
			Serializer::class => $serializer,
			Store::class => $store,
			FieldHydrator::class => $this->hydrator,
		]);

		$uid = $data['uid'] ?? $this->uid->generate();
		$data['uid'] = $uid;
		$owner = new FieldOwner($context, $uid);
		$fieldNames = $this->hydrator->hydrate($node, $data['content'] ?? [], $owner);

		if ($node instanceof HasInit) {
			$node->init();
		}

		self::$nodeState[$node] = [
			'data' => $data,
			'fieldNames' => $fieldNames,
		];

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
	): Node {
		return new Node(
			$node,
			self::fieldNamesFor($node),
			$this->hydrator,
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

	/**
	 * Get the field names for a node instance.
	 */
	public static function fieldNamesFor(object $node): array
	{
		return self::getNodeState($node)['fieldNames'] ?? [];
	}

	private static function getNodeState(object $node): array
	{
		self::$nodeState ??= new WeakMap();
		$node = Node::unwrap($node);

		return self::$nodeState[$node] ?? [];
	}

	/**
	 * Get a metadata value from the raw DB data for a node instance.
	 */
	public static function meta(object $node, string $key): mixed
	{
		return self::dataFor($node)[$key] ?? null;
	}

	public function hydrator(): FieldHydrator
	{
		return $this->hydrator;
	}
}
