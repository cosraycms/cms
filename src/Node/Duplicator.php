<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Cosray\Actor;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Field;
use Cosray\Title\Resolver as TitleResolver;
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
	private readonly TitleResolver $titles;

	/** @var array<string, string> Copy marker per content locale, lazily translated. */
	private array $markers = [];

	public function __construct(
		private readonly Context $context,
		private readonly Cms $cms,
		Types $types,
	) {
		$this->titles = new TitleResolver($types);
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
			// Only the subtree root gets the copy marker: it is the entry
			// the user looks for in the listing afterwards.
			$queue = [[$node, is_string($parent) && trim($parent) !== '' ? $parent : null, true]];

			while ($queue !== []) {
				[$current, $parentUid, $mark] = array_shift($queue);
				$copyUid = $this->copy($current, $parentUid, $actor, $mark);
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
						$queue[] = [$child, $copyUid, false];
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

	private function copy(Wrapper $wrapper, ?string $parentUid, Actor $actor, bool $mark): string
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

		if ($mark && is_array($data['content'] ?? null)) {
			$data['content'] = $this->markCopy($source::class, $data['content']);
		}

		// A blueprint object instead of the source node: the store falls
		// back to node meta for absent data keys (uid, parent, handle),
		// and the source's must not leak into the copy.
		$blueprint = $this->factory->blueprint($source::class, $this->context, $this->cms);
		$result = $this->store->create($blueprint, $data, $this->context->locales(), $actor);

		return $result['uid'];
	}

	/**
	 * Appends the localized copy marker to every non-empty locale value of
	 * the title field, so the copy is identifiable in the listing.
	 * Types without a writable title field stay unmarked.
	 *
	 * @param class-string $class
	 * @param array<string, mixed> $content
	 * @return array<string, mixed>
	 */
	private function markCopy(string $class, array $content): array
	{
		$field = $this->titles->writableField($class);
		$value = $field === null ? null : $content[$field]['value'] ?? null;

		if ($field === null || !is_array($value)) {
			return $content;
		}

		foreach ($value as $locale => $text) {
			if (!is_string($text) || trim($text) === '') {
				continue;
			}

			$content[$field]['value'][$locale] = $text . ' ' . $this->marker((string) $locale);
		}

		return $content;
	}

	/**
	 * The copy marker translated into a content locale (the default locale
	 * for the neutral key), resolved through a briefly activated
	 * per-locale translator so the scanner sees the message id.
	 */
	private function marker(string $localeId): string
	{
		if (array_key_exists($localeId, $this->markers)) {
			return $this->markers[$localeId];
		}

		$locales = $this->context->locales();
		$locale = $localeId === Field::NEUTRAL_LOCALE || !$locales->exists($localeId)
			? $locales->getDefault()
			: $locales->get($localeId);
		$previous = Verba::translator();
		Verba::activate(new Translator($locale->id, $locales->catalogs(), $locale->fallbacks()));

		try {
			$marker = __('node:copy-suffix');
		} finally {
			if ($previous) {
				Verba::activate($previous);
			} else {
				Verba::deactivate();
			}
		}

		return $this->markers[$localeId] = $marker;
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
