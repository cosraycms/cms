<?php

declare(strict_types=1);

namespace Cosray\Node;

use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RuntimeException;

/**
 * Creates CMS nodes without exposing blueprint, serialization, and storage wiring.
 *
 * @api
 */
final class Writer
{
	private readonly Factory $factory;
	private readonly Serializer $serializer;
	private readonly Store $store;

	public function __construct(
		private readonly Context $context,
		private readonly Cms $cms,
		Types $types,
	) {
		$this->factory = $cms->nodeFactory();
		$this->serializer = new Serializer($types, $this->factory->uid());
		$this->store = new Store(
			$context->db,
			new PathManager(),
			$types,
			$this->factory->uid(),
			factory: $this->factory,
			cms: $cms,
			context: $context,
		);
	}

	/**
	 * @param class-string $class
	 * @param array<string, mixed> $values
	 */
	public function draft(string $class, array $values = []): Draft
	{
		$node = $this->factory->blueprint($class, $this->context, $this->cms);
		$data = $this->serializer->blueprint(
			$node,
			Factory::fieldNamesFor($node),
			$this->context->locales(),
			$values,
		);

		return new Draft($node, $data);
	}

	/** @return array{success: true, uid: string} */
	public function create(Draft $draft, ?Actor $actor = null): array
	{
		$this->assertUsablePaths($draft->data());

		return $this->store->create(
			$draft->node,
			$draft->data(),
			$this->context->locales(),
			$actor ?? Actor::system(),
		);
	}

	/**
	 * Explicit paths fail loudly here: `PathManager` would silently
	 * suffix a colliding path, which defeats preserving a legacy URL.
	 */
	private function assertUsablePaths(array $data): void
	{
		$locales = $this->context->locales();

		foreach ($data['paths'] ?? [] as $locale => $path) {
			if (!$path) {
				continue;
			}

			if (!$locales->exists($locale)) {
				throw new RuntimeException(
					"Unknown locale '{$locale}' for the node path '{$path}'",
				);
			}

			if ($this->context->db->nodes->activePathExists(['path' => $path])->first()) {
				throw new RuntimeException("The URL path '{$path}' is already in use");
			}
		}
	}
}
