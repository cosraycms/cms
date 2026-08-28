<?php

declare(strict_types=1);

namespace Cosray\Node;

use Cosray\Actor;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Throwable;

/**
 * Copies a node — with children, its whole subtree — through the regular
 * create pipeline, so validation, reference indexing, title
 * materialization, and path generation all apply to the copies.
 */
final class Duplicator
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
	 * Every copy starts as an unlocked draft with a fresh uid and no
	 * handle. Children are created after their copied parent, so their
	 * generated routes compose under the copy's actual paths.
	 *
	 * @return array{success: true, created: list<string>}
	 */
	public function duplicate(Wrapper $node, Actor $actor, bool $withChildren = false): array
	{
		$db = $this->context->db;
		$ownsTransaction = !$db->getConn()->inTransaction();
		$created = [];

		try {
			if ($ownsTransaction) {
				$db->begin();
			}

			$parent = $node->meta->get('parent');
			$copied = [$node->meta->uid];
			$queue = [[$node, is_string($parent) && trim($parent) !== '' ? $parent : null]];

			while ($queue !== []) {
				[$current, $parentUid] = array_shift($queue);
				$copyUid = $this->copy($current, $parentUid, $actor);
				$created[] = $copyUid;

				if (!$withChildren) {
					break;
				}

				foreach ($this->childUids($current->meta->uid) as $childUid) {
					// A parent cycle would queue forever; unseen sources only.
					if (in_array($childUid, $copied, true)) {
						continue;
					}

					$child = $this->cms->node->byUid($childUid, published: null);

					if ($child) {
						$copied[] = $childUid;
						$queue[] = [$child, $copyUid];
					}
				}
			}

			if ($ownsTransaction) {
				$db->commit();
			}
		} catch (Throwable $e) {
			if ($ownsTransaction) {
				$db->rollback();
			}

			throw new RuntimeException(
				'Error while duplicating: ' . $e->getMessage(),
				(int) $e->getCode(),
				previous: $e,
			);
		}

		return [
			'success' => true,
			'created' => $created,
		];
	}

	private function copy(Wrapper $wrapper, ?string $parentUid, Actor $actor): string
	{
		$source = Wrapper::unwrap($wrapper);
		$data = $this->serializer->read(
			$source,
			Factory::dataFor($source),
			Factory::fieldNamesFor($source),
		);

		// No uid key: the store generates a fresh one with its own retry.
		unset($data['uid']);
		$data['parent'] = $parentUid;
		$data['published'] = false;
		// The store refuses locked payloads, and a locked copy could not
		// be edited afterwards.
		$data['locked'] = false;
		// Handles are unique identities, never copied.
		$data['handle'] = null;
		// Empty paths force regeneration under the copy's parent;
		// PathManager suffixes any collision with the source's paths.
		$data['paths'] = $this->emptyPaths();

		// A blueprint object instead of the source node: the store falls
		// back to node meta for absent data keys (uid, parent, handle),
		// and the source's must not leak into the copy.
		$blueprint = $this->factory->blueprint($source::class, $this->context, $this->cms);
		$result = $this->store->create($blueprint, $data, $this->context->locales(), $actor);

		return $result['uid'];
	}

	/** @return list<string> */
	private function childUids(string $uid): array
	{
		return array_map(
			static fn(array $row): string => (string) $row['uid'],
			$this->context->db->nodes->childUids(['uid' => $uid])->all(),
		);
	}

	private function emptyPaths(): array
	{
		$paths = [];

		foreach ($this->context->locales() as $locale) {
			$paths[$locale->id] = '';
		}

		return $paths;
	}
}
