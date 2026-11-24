<?php

declare(strict_types=1);

namespace Cosray\Node;

use Cosray\Actor;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use DateTimeInterface;

/**
 * Creates and publishes CMS nodes without exposing blueprint, serialization, and storage wiring.
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
		$this->store = Store::of($context, $cms, $types);
	}

	/**
	 * @param class-string $class
	 * @param array<string, mixed> $values
	 */
	public function prepare(string $class, array $values = []): Prepared
	{
		$node = $this->factory->blueprint($class, $this->context, $this->cms);
		$data = $this->serializer->blueprint(
			$node,
			Factory::fieldNamesFor($node),
			$this->context->locales(),
			$values,
		);

		return new Prepared($node, $data);
	}

	/**
	 * The actor is the last editor and, unless overridden, the creator.
	 * Historical dates apply only to the node, not its paths or handle.
	 *
	 * @return array{success: true, uid: string}
	 */
	public function create(
		Prepared $prepared,
		?Actor $actor = null,
		?Actor $creator = null,
		?DateTimeInterface $created = null,
		?DateTimeInterface $changed = null,
	): array {
		$this->assertUsablePaths($prepared->data());

		return $this->store->create(
			$prepared->node,
			$prepared->data(),
			$this->context->locales(),
			$actor ?? Actor::system(),
			$creator,
			$created,
			$changed,
		);
	}

	/**
	 * Brings the node online with its working copy, if it has one, as
	 * applying and publishing in the editor does. Returns whether anything
	 * changed: a published node without a working copy stays as it is.
	 * It checks no permissions: each caller checks them for its user.
	 *
	 * @param array<string, mixed> $cause what the events record as the cause
	 */
	public function publish(string $uid, Actor $actor, array $cause = []): bool
	{
		$node = $this->node($uid);

		if ($this->store->applyDraft($node, $this->context->locales(), $actor, publish: true, cause: $cause)) {
			return true;
		}

		if ((bool) Factory::meta($node, 'published')) {
			return false;
		}

		$this->store->published($node, true, $actor, $cause);

		return true;
	}

	/**
	 * Takes the node offline and leaves its content and working copy alone.
	 * Returns whether anything changed. It checks no permissions.
	 *
	 * @param array<string, mixed> $cause what the event records as the cause
	 */
	public function unpublish(string $uid, Actor $actor, array $cause = []): bool
	{
		$node = $this->node($uid);

		if (!(bool) Factory::meta($node, 'published')) {
			return false;
		}

		$this->store->published($node, false, $actor, $cause);

		return true;
	}

	private function node(string $uid): object
	{
		$node = $this->cms->node->byUid($uid, published: null);

		return $node === null
			? throw new RuntimeException("There is no node with the uid '{$uid}'")
			: Wrapper::unwrap($node);
	}

	/**
	 * Explicit paths fail loudly here: `PathManager` would silently
	 * suffix a colliding path, which defeats preserving a legacy URL.
	 *
	 * @param array<string, mixed> $data
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
