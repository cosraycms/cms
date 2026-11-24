<?php

declare(strict_types=1);

namespace Cosray\Node;

use Cosray\Cms;
use Cosray\Context;
use Cosray\Contract\NodeListener;
use Cosray\Exception\RuntimeException;
use ReflectionClass;

/**
 * The listeners every node write is handed to, in the order they were
 * registered.
 */
final class Listeners
{
	/** @var list<class-string<NodeListener>|NodeListener> */
	private array $listeners = [];

	/** @param class-string<NodeListener>|NodeListener $listener */
	public function add(string|NodeListener $listener): self
	{
		if (is_string($listener)) {
			if (!class_exists($listener) || !is_a($listener, NodeListener::class, true)) {
				throw new RuntimeException('Node listeners must implement ' . NodeListener::class . ": {$listener}");
			}

			if (!new ReflectionClass($listener)->isInstantiable()) {
				throw new RuntimeException("Node listener {$listener} must be instantiable");
			}
		}

		$this->listeners[] = $listener;

		return $this;
	}

	/**
	 * The listeners for one context: a class the container knows is taken
	 * from there, any other is built for the context, as nodes are.
	 *
	 * @return list<NodeListener>
	 */
	public function for(Context $context, ?Cms $cms = null): array
	{
		$types = $cms === null ? [] : [Cms::class => $cms];

		return array_map(static function (string|NodeListener $listener) use ($context, $types): NodeListener {
			if ($listener instanceof NodeListener) {
				return $listener;
			}

			$built = $context->container->has($listener)
				? $context->container->get($listener)
				: $context->create($listener, $types);
			assert($built instanceof NodeListener, 'The registry holds only node listeners');

			return $built;
		}, $this->listeners);
	}
}
