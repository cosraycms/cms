<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Core\Exception\HttpBadRequest;
use Celema\Core\Exception\HttpConflict;
use Celema\Core\Exception\HttpError;
use Celema\Quma\Database;
use Celema\Quma\Exception\UniqueViolation;
use Cosray\Actor;
use Cosray\Block\Registry as Blocks;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\NodeLocked;
use Cosray\Exception\RoutePathError;
use Cosray\Exception\RuntimeException;
use Cosray\Fulltext;
use Cosray\Locale;
use Cosray\Locales;
use Cosray\References;
use Cosray\Richtext\Normalizer;
use Cosray\Title\Resolver as TitleResolver;
use Cosray\Uid;
use Cosray\Validation\ValidatorFactory;
use Cosray\Webhooks;
use DateTimeInterface;
use Throwable;

class Store
{
	private const int CREATE_UID_ATTEMPTS = 5;

	private readonly RoutePathGenerator $routePathGenerator;
	private readonly References\Scanner $scanner;
	private readonly References\Sync $sync;
	private readonly TitleResolver $titleResolver;
	private readonly Drafts $drafts;
	private readonly Fulltext\Sync $fulltext;

	public function __construct(
		private readonly Database $db,
		private readonly PathManager $pathManager,
		private readonly Types $types,
		private readonly Uid $uid,
		?RoutePathGenerator $routePathGenerator = null,
		// Dynamic (Contract\Title) titles read the node's fields, so they must
		// be re-hydrated from the content being saved. These stay optional so
		// a bare Store still works; without them dynamic titles fall back to
		// the passed node and `db:titles` is the authoritative refresh.
		private readonly ?Factory $factory = null,
		private readonly ?Cms $cms = null,
		private readonly ?Context $context = null,
	) {
		$this->routePathGenerator = $routePathGenerator ?? new RoutePathGenerator($db, $types);
		$this->scanner = new References\Scanner();
		$this->sync = new References\Sync($db);
		$this->titleResolver = new TitleResolver($types);
		$this->drafts = new Drafts($db);
		$this->fulltext = new Fulltext\Sync($db, new Fulltext\Builder(
			$types,
			$factory?->hydrator()->services()->blocks ?? Blocks::withDefaults(),
		));
	}

	/**
	 * Writes the live row. A pending working copy is left alone: this is
	 * the API path, and the editor's publish() is what supersedes a draft.
	 *
	 * @param array<string, mixed> $data
	 * @return array{success: true, uid: string}
	 */
	public function save(
		object $node,
		array $data,
		Locales $locales,
		Actor $actor,
		bool $create = false,
	): array {
		$this->fulltext->validate($locales);
		$data = $this->prepare($node, $data, $locales, $create);

		$this->transaction(function () use ($node, $data, $actor, $locales, $create): void {
			$this->lock($data['uid']);

			if ($this->persist($node, $data, $actor->id, $locales, $create)) {
				$this->event($create ? 'node.created' : 'node.updated', $node, $data['uid']);
			}
		}, 'Error while saving: ');

		return ['success' => true, 'uid' => $data['uid']];
	}

	/**
	 * Writes the given state as the node's main version and drops the
	 * working copy it supersedes. Whether visitors see it is the node's
	 * `published` flag, which the state carries.
	 *
	 * @param array<string, mixed> $data
	 * @return array{success: true, uid: string}
	 */
	public function apply(object $node, array $data, Locales $locales, Actor $actor): array
	{
		$this->fulltext->validate($locales);
		$data = $this->prepare($node, $data, $locales);
		$nodeId = $this->nodeId($node);

		$this->transaction(function () use ($node, $data, $locales, $actor, $nodeId): void {
			$this->lock($data['uid']);
			$changed = $this->persist($node, $data, $actor->id, $locales);

			if ($this->drafts->delete($nodeId, applied: true)) {
				$this->sync->remove('draft', $data['uid']);
				$changed = true;
			}

			if ($changed) {
				$this->event('node.updated', $node, $data['uid']);
			}
		}, 'Error while applying: ');

		return ['success' => true, 'uid' => $data['uid']];
	}

	/**
	 * Saves the working copy of a node: changes to its main version that
	 * wait for apply(). A working copy that equals the main version is
	 * dropped, not saved.
	 *
	 * @param array<string, mixed> $data
	 * @return array{success: true, uid: string}
	 */
	public function draft(object $node, array $data, Locales $locales, Actor $actor): array
	{
		if (!$this->holdsDrafts($node)) {
			throw new RuntimeException('Only a stored node that is no record can hold a working copy');
		}

		$data = $this->prepare($node, $data, $locales);
		$paths = $this->submittedPaths($data);
		// Refused now rather than when publish() applies the paths.
		$this->pathManager->reserved->assertFree($paths);
		$nodeId = $this->nodeId($node);
		$content = is_array($data['content'] ?? null) ? $data['content'] : [];
		$handle = $this->resolveHandle($data);

		$this->transaction(function () use ($node, $data, $actor, $nodeId, $content, $handle, $paths): void {
			$this->lock($data['uid']);

			if ($this->drafts->matchesLive($nodeId, $content, $handle, $paths)) {
				$changed = $this->drafts->delete($nodeId);

				if ($changed) {
					$this->sync->remove('draft', $data['uid']);
				}
			} else {
				$changed = $this->drafts->save(
					$nodeId,
					$content,
					['handle' => $handle, 'paths' => $paths],
					$actor->id,
				);

				if ($changed) {
					$this->sync->replace('draft', $data['uid'], $this->scanner->scan($content));
				}
			}

			if ($changed) {
				$this->event('node.draft', $node, $data['uid']);
			}
		}, 'Error while saving the working copy: ');

		return ['success' => true, 'uid' => $data['uid']];
	}

	public function discard(object $node): void
	{
		$nodeId = $this->nodeId($node);
		$uid = (string) Factory::meta($node, 'uid');

		$this->transaction(function () use ($node, $nodeId, $uid): void {
			$this->lock($uid);

			if ($this->drafts->delete($nodeId)) {
				$this->sync->remove('draft', $uid);
				$this->event('node.draft', $node, $uid);
			}
		}, 'Error while discarding: ');
	}

	/** Publishes the node or withdraws it, leaving its content and working copy alone. */
	public function published(object $node, bool $published, Actor $actor): void
	{
		$uid = (string) Factory::meta($node, 'uid');

		$this->transaction(function () use ($node, $uid, $published, $actor): void {
			$this->lock($uid);
			$this->db->nodes->setPublished(['uid' => $uid, 'published' => $published, 'editor' => $actor->id])->run();
			$this->event('node.updated', $node, $uid);
		}, 'Error while publishing: ');
	}

	/** Hides the node from listings or shows it again, applied immediately. */
	public function hide(object $node, bool $hidden, Actor $actor): void
	{
		$uid = (string) Factory::meta($node, 'uid');

		$this->transaction(function () use ($node, $uid, $hidden, $actor): void {
			$this->lock($uid);
			$this->db->nodes->setHidden([
				'node' => $this->nodeId($node),
				'hidden' => $hidden,
				'editor' => $actor->id,
			])->run();
			$this->event('node.updated', $node, $uid);
		}, 'Error while hiding the node: ');
	}

	/**
	 * Applies the working copy, if there is one, to the main version and,
	 * asked to, publishes the node with it.
	 */
	public function applyDraft(object $node, Locales $locales, Actor $actor, bool $publish = false): bool
	{
		$draft = $this->drafts->get($this->nodeId($node));

		if ($draft === null) {
			return false;
		}

		$published = $publish || (bool) Factory::meta($node, 'published');
		$this->apply($node, $this->workingData($node, $draft, published: $published), $locales, $actor);

		return true;
	}

	/**
	 * Holds the node until the transaction ends, so writers of one node take
	 * turns. The uid lock also covers a node still to be created; the row
	 * lock also stops writers that update the row without this store.
	 */
	public function lock(string $uid): void
	{
		$this->db->nodes->lock(['uid' => $uid])->run();
		$this->db->nodes->lockRow(['uid' => $uid])->run();
	}

	/**
	 * Every stored node keeps its changes in a working copy until they are
	 * applied, except a record, which is saved directly.
	 */
	public function holdsDrafts(object $node): bool
	{
		return (
			!(bool) $this->types->get($node::class, 'record', false)
				&& Factory::meta($node, 'deleted') === null
		);
	}

	/**
	 * The save payload for a node's working copy: the drafted content,
	 * handle and paths over the live flags.
	 *
	 * @param array{content: array<string, mixed>, settings: array<string, mixed>} $draft
	 * @return array<string, mixed>
	 */
	private function workingData(object $node, array $draft, bool $published): array
	{
		$settings = $draft['settings'];
		$paths = $settings['paths'] ?? Factory::meta($node, 'paths');

		return [
			'uid' => (string) Factory::meta($node, 'uid'),
			'handle' => array_key_exists('handle', $settings)
				? $settings['handle']
				: Factory::meta($node, 'handle'),
			'published' => $published,
			'hidden' => (bool) Factory::meta($node, 'hidden'),
			'locked' => (bool) Factory::meta($node, 'locked'),
			'paths' => is_array($paths) ? $paths : [],
			'content' => $draft['content'],
		];
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, string>
	 */
	private function submittedPaths(array $data): array
	{
		$paths = [];

		foreach (is_array($data['paths'] ?? null) ? $data['paths'] : [] as $locale => $path) {
			if (is_string($locale) && is_string($path)) {
				$paths[$locale] = $path;
			}
		}

		return $paths;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<mixed>
	 */
	private function prepare(object $node, array $data, Locales $locales, bool $create = false): array
	{
		$data = $this->normalizeSubmittedHandle($data);
		$data = $this->validate($node, $data, $locales);

		if (is_array($data['content'] ?? null)) {
			// Richtext documents persist in canonical form (byte-stable
			// storage for history diffs); empty documents become null.
			$data['content'] = new Normalizer()->content($data['content']);
		}

		if (!$create) {
			$this->assertUidUnchanged($node, $data);
		}

		$data = $this->completeHandle($node, $data);

		if ($data['locked']) {
			throw new HttpBadRequest(payload: ['message' => __('node:locked')]);
		}

		return $data;
	}

	/**
	 * Queues the webhook deliveries of a write inside its transaction, so
	 * they commit or roll back with it. A descendant deleted with its parent
	 * is reported with the parent's type.
	 */
	private function event(string $event, object $node, string $uid): void
	{
		$container = $this->context?->container;
		$webhooks = $container !== null && $container->has(Webhooks::class) ? $container->get(Webhooks::class) : null;

		if ($webhooks instanceof Webhooks) {
			$webhooks->record($event, ['uid' => $uid, 'type' => (string) $this->types->get($node::class, 'handle')]);
		}
	}

	private function transaction(callable $work, string $failure): void
	{
		try {
			$this->db->transaction($work);
		} catch (Throwable $e) {
			if ($e instanceof HttpError) {
				throw $e;
			}

			throw new RuntimeException(
				$failure . $e->getMessage(),
				(int) $e->getCode(),
				previous: $e,
			);
		}
	}

	private function nodeId(object $node): int
	{
		$nodeId = Factory::meta($node, 'node');

		if (!is_int($nodeId) && !is_string($nodeId)) {
			throw new RuntimeException('Missing node id for update');
		}

		return (int) $nodeId;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array{success: true, uid: string}
	 */
	public function create(
		object $node,
		array $data,
		Locales $locales,
		Actor $actor,
		?Actor $creator = null,
		?DateTimeInterface $created = null,
		?DateTimeInterface $changed = null,
	): array {
		$this->fulltext->validate($locales);
		$metadata = [
			'creator' => ($creator ?? $actor)->id,
			'created' => $created?->format('Y-m-d\TH:i:s.uP'),
			'changed' => $changed?->format('Y-m-d\TH:i:s.uP'),
		];
		$generatedUid = !array_key_exists('uid', $data);
		if ($generatedUid) {
			$data['uid'] = Factory::meta($node, 'uid') ?? $this->uid->generate();
		}
		$attempts = $generatedUid ? self::CREATE_UID_ATTEMPTS : 1;

		for ($attempt = 1; $attempt <= $attempts; $attempt++) {
			if ($generatedUid && $attempt > 1) {
				$data['uid'] = $this->uid->generate();
			}

			try {
				$prepared = $this->prepare($node, $data, $locales, create: true);
				$this->transaction(function () use ($node, $prepared, $actor, $locales, $metadata): void {
					$this->lock($prepared['uid']);
					$this->persist($node, $prepared, $actor->id, $locales, create: true, metadata: $metadata);
					$this->event('node.created', $node, $prepared['uid']);
				}, 'Error while saving: ');

				return ['success' => true, 'uid' => $prepared['uid']];
			} catch (HttpConflict $e) {
				if (!$generatedUid || $attempt === $attempts) {
					throw $e;
				}
			}
		}

		throw new RuntimeException('Could not generate a unique node uid');
	}

	/**
	 * A locked node is refused, and so is a subtree holding one.
	 *
	 * @return array{success: true, error: false, deleted: list<string>}
	 */
	public function delete(object $node, Actor $actor, bool $withChildren = false): array
	{
		$uid = (string) Factory::meta($node, 'uid');

		if ((bool) Factory::meta($node, 'locked')) {
			throw new NodeLocked(payload: ['message' => __('node:locked')]);
		}

		if (!$withChildren && $this->childUids($uid) !== []) {
			throw new HttpConflict(payload: ['message' => __('node:has-children')]);
		}

		$uids = $withChildren ? $this->subtreeUids($uid) : [$uid];

		$this->transaction(function () use ($node, $uid, $uids, $actor): void {
			$this->lock($uid);

			foreach ($uids as $deleteUid) {
				$this->db->nodes->delete([
					'uid' => $deleteUid,
					'editor' => $actor->id,
				])->run();
				$this->event('node.deleted', $node, $deleteUid);
			}
		}, 'Error while deleting: ');

		return [
			'success' => true,
			'error' => false,
			'deleted' => $uids,
		];
	}

	/**
	 * The node and all its non-deleted descendants, children before
	 * parents so a partial failure never leaves a reachable orphan.
	 *
	 * @return list<string>
	 */
	private function subtreeUids(string $uid): array
	{
		$uids = [$uid];
		$queue = [$uid];

		while ($queue !== []) {
			$current = array_shift($queue);

			foreach ($this->children($current) as $child) {
				// A parent cycle would loop forever; unseen uids only.
				if (in_array($child['uid'], $uids, true)) {
					continue;
				}

				if ($child['locked']) {
					throw new NodeLocked(payload: ['message' => __('node:locked-descendant')]);
				}

				$uids[] = $child['uid'];
				$queue[] = $child['uid'];
			}
		}

		return array_reverse($uids);
	}

	/** @return list<string> */
	private function childUids(string $uid): array
	{
		return array_column($this->children($uid), 'uid');
	}

	/** @return list<array{uid: string, locked: bool}> */
	private function children(string $uid): array
	{
		return array_map(
			static fn(array $row): array => ['uid' => (string) $row['uid'], 'locked' => (bool) $row['locked']],
			$this->db->nodes->childUids(['uid' => $uid])->all(),
		);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<mixed>
	 */
	public function validate(object $node, array $data, Locales $locales): array
	{
		$factory = new ValidatorFactory($node, $locales);
		$shape = $factory->create();
		$result = $shape->validate($data);

		if (!$result->valid()) {
			throw new HttpBadRequest(payload: [
				'message' => __('node:invalid-data'),
				'errors' => $result->issues(),
			]);
		}

		return $result->values();
	}

	/**
	 * Writes what differs from the stored node and says whether anything
	 * did: the row, the handle or a URL path.
	 *
	 * @param array{creator?: int, created?: ?string, changed?: ?string} $metadata
	 * @param array<mixed> $data
	 */
	private function persist(
		object $node,
		array $data,
		int $editor,
		Locales $locales,
		bool $create = false,
		array $metadata = [],
	): bool {
		$parentUid = $this->resolveParentUid($node, $data);
		$parentId = $this->resolveParentId($parentUid);
		$handle = $this->resolveHandle($data);

		// Materialize the title alongside the content so both ride one UPDATE
		// (a single change/history record instead of two).
		$data['title'] = $this->materializeTitle($node, $data, $locales);

		[$nodeId, $rowChanged] = $this->persistNode($node, $data, $editor, $parentId, $create, $metadata);
		$handleChanged = $this->persistHandle($nodeId, $handle, $editor);

		if (!$create) {
			$this->db->positions->leaveParent(['node' => $nodeId, 'parent' => $parentId])->run();
		}

		// The reference indexes ride in the save transaction: full
		// replace per owner from the content just written.
		if ($rowChanged) {
			$this->sync->replace('node', $data['uid'], $this->scanner->scan($data['content'] ?? []));
			$this->fulltext->replace(
				$nodeId,
				$data['uid'],
				$node::class,
				$data['content'] ?? [],
				$data['title'],
				$locales,
			);
		}

		$pathsChanged = false;

		if ((bool) $this->types->get($node::class, 'routable', false)) {
			$this->ensureRouteHandle($node, $handle);
			$data = $this->completeGeneratedPaths($node, $data, $locales, $parentId);
			$pathsChanged = $this->pathManager->persist($this->db, $data, $editor, $nodeId, $locales);
		}

		// `changed` dates the whole node, which API clients sync by.
		if (!$rowChanged && ($handleChanged || $pathsChanged)) {
			$this->db->nodes->touch(['node' => $nodeId, 'editor' => $editor])->run();
		}

		return $rowChanged || $handleChanged || $pathsChanged;
	}

	/**
	 * The materialized title map for the content being saved. Mirrors
	 * `Wrapper::title()`'s resolution but yields every locale.
	 *
	 * @param array<mixed> $data
	 * @return array<string, string>
	 */
	private function materializeTitle(
		object $node,
		array $data,
		Locales $locales,
	): array {
		$content = is_array($data['content'] ?? null) ? $data['content'] : [];
		$descriptor = $this->titleResolver->descriptor($node::class);

		return match ($descriptor['kind']) {
			TitleResolver::KIND_FIELD => $this->titleResolver->fieldMap($content, $descriptor['field']),
			TitleResolver::KIND_DYNAMIC => $this->dynamicTitle($node, $data, $locales),
			default => [],
		};
	}

	/**
	 * Evaluate a dynamic title once per locale against the content being
	 * saved, restoring the request locale afterwards.
	 *
	 * @param array<mixed> $data
	 * @return array<string, string>
	 */
	private function dynamicTitle(object $node, array $data, Locales $locales): array
	{
		$target = $this->titleEvalNode($node, $data);
		$provider = $this->titleResolver->provider($target);

		if ($provider === null) {
			return [];
		}

		if ($this->context === null) {
			return $this->titleResolver->dynamicMap(
				static fn(Locale $locale): string => $provider->title(),
				$locales,
			);
		}

		return $this->titleResolver->dynamicMap(
			fn(Locale $locale): string => $this->context->withLocale(
				$locale,
				$provider->title(...),
			),
			$locales,
		);
	}

	/**
	 * A node instance whose fields reflect the content being saved. When the
	 * factory seam is wired the node is re-hydrated from that content (the
	 * passed instance still carries the previous version); otherwise the
	 * passed node is used and `db:titles` remains the authoritative refresh.
	 *
	 * @param array<mixed> $data
	 */
	private function titleEvalNode(object $node, array $data): object
	{
		if ($this->factory !== null && $this->cms !== null && $this->context !== null) {
			return $this->factory->create($node::class, $this->context, $this->cms, [
				'uid' => $data['uid'],
				'content' => is_array($data['content'] ?? null) ? $data['content'] : [],
			]);
		}

		return $node;
	}

	/**
	 * The node id, and whether the row changed.
	 *
	 * @param array{creator?: int, created?: ?string, changed?: ?string} $metadata
	 * @param array<mixed> $data
	 * @return array{int, bool}
	 */
	private function persistNode(
		object $node,
		array $data,
		int $editor,
		?int $parent,
		bool $create,
		array $metadata,
	): array {
		$class = $node::class;
		$handle = (string) $this->types->get($class, 'handle');
		$this->ensureTypeExists($handle);
		$params = [
			'uid' => $data['uid'],
			'parent' => $parent,
			'hidden' => $data['hidden'],
			'published' => $data['published'],
			'locked' => $data['locked'],
			'type' => $handle,
			'content' => json_encode($data['content']),
			'title' => json_encode($data['title'] ?? []),
			'editor' => $editor,
		];

		if (!$create) {
			$nodeId = $this->nodeId($node);
			$saved = $this->db
				->nodes
				->save([
					'node' => $nodeId,
					'parent' => $params['parent'],
					'hidden' => $params['hidden'],
					'published' => $params['published'],
					'locked' => $params['locked'],
					'content' => $params['content'],
					'title' => $params['title'],
					'editor' => $params['editor'],
				])
				->first();

			return [$nodeId, $saved !== null];
		}

		$result = $this->db
			->nodes
			->create([
				...$params,
				'creator' => $metadata['creator'] ?? $editor,
				'created' => $metadata['created'] ?? null,
				'changed' => $metadata['changed'] ?? null,
			])
			->first();

		if (!$result) {
			throw new HttpConflict(payload: [
				'message' => __('node:duplicate-uid', ['uid' => $data['uid']]),
			]);
		}

		return [(int) $result['node'], true];
	}

	/** Whether the handle changed. */
	private function persistHandle(int $nodeId, ?string $handle, int $editor): bool
	{
		if ($handle === null) {
			return $this->db->nodes->deleteHandle(['node' => $nodeId])->first() !== null;
		}

		$collision = $this->db
			->nodes
			->handleUidCollision(['handle' => $handle, 'node' => $nodeId])
			->first();

		if ($collision) {
			throw new HttpConflict(payload: [
				'message' => __('node:duplicate-handle-uid', ['handle' => $handle]),
			]);
		}

		try {
			return $this->db
				->nodes
				->saveHandle([
					'node' => $nodeId,
					'handle' => $handle,
					'editor' => $editor,
				])
				->first() !== null;
		} catch (UniqueViolation) {
			throw new HttpConflict(payload: [
				'message' => __('node:duplicate-handle', ['handle' => $handle]),
			]);
		}
	}

	/** @param array<mixed> $data */
	private function resolveParentUid(object $node, array $data): ?string
	{
		$parentUid = array_key_exists('parent', $data)
			? $data['parent']
			: Factory::meta($node, 'parent');

		if ($parentUid === null) {
			return null;
		}

		if (!is_string($parentUid)) {
			throw new HttpBadRequest(payload: [
				'message' => __('node:parent-not-string'),
			]);
		}

		$parentUid = trim($parentUid);

		if ($parentUid === '') {
			return null;
		}

		return $parentUid;
	}

	private function resolveParentId(?string $parentUid): ?int
	{
		if ($parentUid === null) {
			return null;
		}

		$parent = $this->db
			->nodes
			->parentIdByUid(['uid' => $parentUid])
			->first();

		if (!$parent) {
			throw new HttpBadRequest(payload: [
				'message' => __('node:invalid-parent', ['uid' => $parentUid]),
			]);
		}

		return (int) $parent['node'];
	}

	/** @param array<mixed> $data */
	private function assertUidUnchanged(object $node, array $data): void
	{
		$uid = Factory::meta($node, 'uid');

		if (!is_string($uid) || $data['uid'] === $uid) {
			return;
		}

		throw new HttpBadRequest(payload: [
			'message' => __('node:uid-immutable'),
		]);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function normalizeSubmittedHandle(array $data): array
	{
		if (!array_key_exists('handle', $data) || !is_string($data['handle'])) {
			return $data;
		}

		$handle = trim($data['handle']);
		$data['handle'] = $handle === '' ? null : $handle;

		return $data;
	}

	/**
	 * @param array<mixed> $data
	 * @return array<mixed>
	 */
	private function completeHandle(object $node, array $data): array
	{
		if (!array_key_exists('handle', $data)) {
			$data['handle'] = Factory::meta($node, 'handle');
		}

		$data['handle'] = $this->resolveHandle($data);

		return $data;
	}

	/** @param array<mixed> $data */
	private function resolveHandle(array $data): ?string
	{
		$handle = $data['handle'] ?? null;

		if (!is_string($handle)) {
			return null;
		}

		$handle = trim($handle);

		return $handle === '' ? null : $handle;
	}

	private function ensureRouteHandle(object $node, ?string $handle): void
	{
		$route = $this->types->get($node::class, 'route');

		if (!$this->routeNeedsHandle($route) || $handle !== null) {
			return;
		}

		throw new HttpBadRequest(payload: [
			'message' => __('node:handle-required'),
		]);
	}

	/**
	 * @param array<mixed> $data
	 * @return array<mixed>
	 */
	private function completeGeneratedPaths(
		object $node,
		array $data,
		Locales $locales,
		?int $parentId,
	): array {
		if (!$this->needsGeneratedPaths($data)) {
			return $data;
		}

		try {
			$data['generatedPaths'] = $this->routePathGenerator->generate(
				$node::class,
				$data,
				$locales,
				$parentId,
			);
		} catch (RoutePathError $e) {
			throw new HttpBadRequest(
				payload: [
					'message' => $e->getMessage(),
				],
				previous: $e,
			);
		}

		return $data;
	}

	/** @param array<mixed> $data */
	private function needsGeneratedPaths(array $data): bool
	{
		foreach ($data['paths'] ?? [] as $path) {
			if (is_string($path) ? trim($path) !== '' : (bool) $path) {
				return false;
			}
		}

		return true;
	}

	private function routeNeedsHandle(mixed $route): bool
	{
		if (is_string($route)) {
			return $this->routeContainsHandle($route);
		}

		if (!is_array($route)) {
			return false;
		}

		foreach ($route as $localizedRoute) {
			if (is_string($localizedRoute) && $this->routeContainsHandle($localizedRoute)) {
				return true;
			}
		}

		return false;
	}

	private function routeContainsHandle(string $route): bool
	{
		return preg_match('/\{\s*handle\s*(?:\|\s*[^{}|]+\s*)*\}/', $route) === 1;
	}

	private function ensureTypeExists(string $handle): void
	{
		$type = $this->db->nodes->type(['handle' => $handle])->first();

		if (!$type) {
			$this->db->nodes->addType([
				'handle' => $handle,
			])->run();
		}
	}
}
