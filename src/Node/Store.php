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

/**
 * @phpstan-type Action array{action: string, revision?: ?int, data?: array<string, mixed>}
 */
class Store
{
	private const int CREATE_UID_ATTEMPTS = 5;

	/** The webhook event each action fires. */
	private const array HOOKS = [
		'created' => 'node.created',
		'saved' => 'node.updated',
		'applied' => 'node.updated',
		'published' => 'node.updated',
		'unpublished' => 'node.updated',
		'hidden' => 'node.updated',
		'shown' => 'node.updated',
		'moved' => 'node.updated',
		'drafted' => 'node.draft',
		'discarded' => 'node.draft',
		'deleted' => 'node.deleted',
	];

	/** Actions that leave `changed` alone. */
	private const array UNDATED = ['drafted', 'discarded', 'locked', 'unlocked'];

	/** Actions that change the main version, and so `edited`. */
	private const array EDITS = ['created', 'saved', 'applied'];

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
	 * Writes the main version directly. A pending working copy is left
	 * alone: this is the API path, and the editor's apply() is what
	 * supersedes a draft.
	 *
	 * @param array<string, mixed> $data
	 * @return array{success: true, uid: string}
	 */
	public function save(object $node, array $data, Locales $locales, Actor $actor): array
	{
		return $this->saveMain($node, $data, $locales, $actor);
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
			$at = $this->now();
			$working = $this->drafts->get($nodeId);
			$changes = $this->persist($node, $data, $actor->id, $locales);
			$actions = [];

			if ($working !== null) {
				$this->drafts->delete($nodeId);
				$this->sync->remove('draft', $data['uid']);
			}

			if ($changes['edited'] || $working !== null) {
				$revision = $working !== null && $this->holdsState($nodeId, $working['revision'])
					? $this->point($nodeId, $working['revision'])
					: $this->revise($nodeId, $working['revision'] ?? $changes['revision'], $actor, $at);
				$actions[] = ['action' => $this->holdsDrafts($node) ? 'applied' : 'saved', 'revision' => $revision];
			}

			$this->record($node, $nodeId, $data['uid'], $actor, [...$actions, ...$changes['actions']], $at);
		}, 'Error while applying: ');

		return ['success' => true, 'uid' => $data['uid']];
	}

	/**
	 * Saves the working copy of a node: changes to its main version that
	 * wait for apply(). A working copy that equals the main version is
	 * dropped, not saved. `$rebase` says that the main version took the
	 * same change just before, as an API patch of both does.
	 *
	 * @param array<string, mixed> $data
	 * @return array{success: true, uid: string}
	 */
	public function draft(object $node, array $data, Locales $locales, Actor $actor, bool $rebase = false): array
	{
		return $this->saveDraft($node, $data, $locales, $actor, $rebase);
	}

	/**
	 * Drops the node's working copy and returns the revision it held, which
	 * stays in the history.
	 */
	public function discard(object $node, Actor $actor): ?int
	{
		$nodeId = $this->nodeId($node);
		$uid = (string) Factory::meta($node, 'uid');

		return $this->transaction(function () use ($node, $nodeId, $uid, $actor): ?int {
			$this->lock($uid);
			$dropped = $this->drafts->delete($nodeId);

			if ($dropped !== null) {
				$this->sync->remove('draft', $uid);
				$this->record($node, $nodeId, $uid, $actor, [['action' => 'discarded', 'revision' => $dropped]]);
			}

			return $dropped;
		}, 'Error while discarding: ');
	}

	/**
	 * Copies an earlier revision of the node into its working copy, or into
	 * the main version for a node without drafts. History is never
	 * rewritten: the copy is a new revision.
	 */
	public function revert(object $node, int $revision, Locales $locales, Actor $actor): void
	{
		$row = $this->db->revisions->get(['revision' => $revision])->first();

		if (!$row || (int) $row['node'] !== $this->nodeId($node)) {
			throw new HttpBadRequest(payload: ['message' => __('node:unknown-revision')]);
		}

		$content = json_decode((string) $row['content'], true);
		$settings = json_decode((string) $row['settings'], true);
		$data = $this->workingData(
			$node,
			['content' => is_array($content) ? $content : [], 'settings' => is_array($settings) ? $settings : []],
			published: (bool) Factory::meta($node, 'published'),
		);
		$cause = ['source' => 'revert', 'of' => $revision];

		if ($this->holdsDrafts($node)) {
			$this->saveDraft($node, $data, $locales, $actor, cause: $cause);

			return;
		}

		$this->saveMain($node, $data, $locales, $actor, $cause);
	}

	/** Publishes the node or withdraws it, leaving its content and working copy alone. */
	public function published(object $node, bool $published, Actor $actor): void
	{
		$this->switch($node, $actor, published: $published);
	}

	/** Hides the node from listings or shows it again, applied immediately. */
	public function hide(object $node, bool $hidden, Actor $actor): void
	{
		$this->switch($node, $actor, hidden: $hidden);
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
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $cause what the event records as the cause
	 * @return array{success: true, uid: string}
	 */
	private function saveMain(object $node, array $data, Locales $locales, Actor $actor, array $cause = []): array
	{
		$this->fulltext->validate($locales);
		$data = $this->prepare($node, $data, $locales);

		$this->transaction(function () use ($node, $data, $actor, $locales, $cause): void {
			$this->lock($data['uid']);
			$at = $this->now();
			$changes = $this->persist($node, $data, $actor->id, $locales);
			$actions = $changes['edited']
				? [[
					'action' => 'saved',
					'revision' => $this->revise($changes['node'], $changes['revision'], $actor, $at),
					'data' => $cause,
				]]
				: [];
			$this->record($node, $changes['node'], $data['uid'], $actor, [...$actions, ...$changes['actions']], $at);
		}, 'Error while saving: ');

		return ['success' => true, 'uid' => $data['uid']];
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $cause what the event records as the cause
	 * @return array{success: true, uid: string}
	 */
	private function saveDraft(
		object $node,
		array $data,
		Locales $locales,
		Actor $actor,
		bool $rebase = false,
		array $cause = [],
	): array {
		if (!$this->holdsDrafts($node)) {
			throw new RuntimeException('Only a stored node that is no record can hold a working copy');
		}

		$data = $this->prepare($node, $data, $locales);
		$paths = $this->submittedPaths($data);
		// Refused now rather than when apply() applies the paths.
		$this->pathManager->reserved->assertFree($paths);
		$nodeId = $this->nodeId($node);
		$content = is_array($data['content'] ?? null) ? $data['content'] : [];
		$handle = $this->resolveHandle($data);

		$this->transaction(function () use (
			$node,
			$data,
			$actor,
			$nodeId,
			$content,
			$handle,
			$paths,
			$rebase,
			$cause,
		): void {
			$this->lock($data['uid']);

			if ($this->drafts->matchesLive($nodeId, $content, $handle, $paths)) {
				$dropped = $this->drafts->delete($nodeId);

				if ($dropped !== null) {
					$this->sync->remove('draft', $data['uid']);
					$this->record($node, $nodeId, $data['uid'], $actor, [[
						'action' => 'discarded',
						'revision' => $dropped,
					]]);
				}

				return;
			}

			$revision = $this->drafts->save(
				$nodeId,
				$content,
				['handle' => $handle, 'paths' => $paths],
				$actor->id,
				$this->mainRevision($nodeId),
				$rebase,
			);

			if ($revision !== null) {
				$this->sync->replace('draft', $data['uid'], $this->scanner->scan($content));
				$this->record($node, $nodeId, $data['uid'], $actor, [
					['action' => 'drafted', 'revision' => $revision, 'data' => $cause],
				]);
			}
		}, 'Error while saving the working copy: ');

		return ['success' => true, 'uid' => $data['uid']];
	}

	private function switch(object $node, Actor $actor, ?bool $published = null, ?bool $hidden = null): void
	{
		$this->assertPublication($node, (bool) $published, (bool) $hidden);
		$uid = (string) Factory::meta($node, 'uid');
		$nodeId = $this->nodeId($node);

		$this->transaction(function () use ($node, $uid, $nodeId, $published, $hidden, $actor): void {
			$this->lock($uid);

			if ($this->hasPublication($node)) {
				$this->record(
					$node,
					$nodeId,
					$uid,
					$actor,
					$this->persistPublication($nodeId, $published, $hidden, $this->mainRevision($nodeId)),
				);
			}
		}, 'Error while publishing: ');
	}

	/** The time of a write, which its revision, its events and the node's dates share. */
	private function now(): string
	{
		return (string) $this->db->execute('SELECT clock_timestamp() AS now')->one()['now'];
	}

	private function mainRevision(int $nodeId): ?int
	{
		$revision = $this->db->drafts->live(['node' => $nodeId])->one()['revision'];

		return $revision === null ? null : (int) $revision;
	}

	/** Makes the node's stored state a new main revision. */
	private function revise(int $nodeId, ?int $base, Actor $actor, ?string $at): int
	{
		return (int) $this->db->revisions->main([
			'node' => $nodeId,
			'base' => $base,
			'editor' => $actor->id,
			'created' => $at,
		])->one()['revision'];
	}

	private function point(int $nodeId, int $revision): int
	{
		$this->db->revisions->point(['node' => $nodeId, 'revision' => $revision])->run();

		return $revision;
	}

	/**
	 * Whether the node's stored state is exactly what the revision holds:
	 * a working copy applied unchanged becomes the main revision as it is.
	 */
	private function holdsState(int $nodeId, int $revision): bool
	{
		return (bool) $this->db->revisions->matches(['node' => $nodeId, 'revision' => $revision])->one()['matches'];
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
	 * Records the actions of a write as events, all at the same time, and
	 * dates the node by it; `edited` moves only with the main version.
	 * The webhook deliveries are queued in the same transaction, each event
	 * once per write; a node's creation stands for its other changes. A
	 * descendant deleted with its parent is reported with the parent's type.
	 *
	 * @param list<Action> $actions
	 * @param ?string $at the time of the actions, the current time without it
	 * @param ?string $stamp the time the node is dated by instead, for imports
	 */
	private function record(
		object $node,
		int $nodeId,
		string $uid,
		Actor $actor,
		array $actions,
		?string $at = null,
		?string $stamp = null,
	): void {
		$hooks = [];

		foreach ($actions as $action) {
			$at = (string) $this->db->events->record([
				'node' => $nodeId,
				'actor' => $actor->id,
				'action' => $action['action'],
				'revision' => $action['revision'] ?? null,
				'data' => json_encode((object) ($action['data'] ?? [])),
				'at' => $at,
			])->one()['at'];
			$hooks[self::HOOKS[$action['action']] ?? ''] = true;
		}

		$names = array_column($actions, 'action');

		if ($at !== null && array_diff($names, self::UNDATED) !== []) {
			$this->db->nodes->stamp([
				'node' => $nodeId,
				'at' => $stamp ?? $at,
				'edited' => array_intersect($names, self::EDITS) !== [],
			])->run();
		}

		unset($hooks['']);

		if (isset($hooks['node.created'])) {
			$hooks = ['node.created' => true];
		}

		$container = $this->context?->container;
		$webhooks = $container !== null && $container->has(Webhooks::class) ? $container->get(Webhooks::class) : null;

		if (!$webhooks instanceof Webhooks) {
			return;
		}

		foreach (array_keys($hooks) as $hook) {
			$webhooks->record($hook, ['uid' => $uid, 'type' => (string) $this->types->get($node::class, 'handle')]);
		}
	}

	/**
	 * Writes the node's publication and returns the actions it took, which
	 * concern the main revision: a null value keeps the stored one.
	 *
	 * @return list<Action>
	 */
	private function persistPublication(int $nodeId, ?bool $published, ?bool $hidden, ?int $revision): array
	{
		$row = $this->db
			->publications
			->save([
				'node' => $nodeId,
				'published' => $published,
				'hidden' => $hidden,
			])
			->first();

		if ($row === null) {
			return [];
		}

		$actions = [];
		$was = static fn(mixed $value): bool => (bool) $value;

		if ((bool) $row['published'] !== $was($row['was_published'])) {
			$actions[] = ['action' => (bool) $row['published'] ? 'published' : 'unpublished', 'revision' => $revision];
		}

		if ((bool) $row['hidden'] !== $was($row['was_hidden'])) {
			$actions[] = ['action' => (bool) $row['hidden'] ? 'hidden' : 'shown', 'revision' => $revision];
		}

		return $actions;
	}

	/** Records have no publication: they are private and never live. */
	public function hasPublication(object $node): bool
	{
		return !(bool) $this->types->get($node::class, 'record', false);
	}

	private function assertPublication(object $node, bool $published, bool $hidden): void
	{
		if (($published || $hidden) && !$this->hasPublication($node)) {
			throw new HttpBadRequest(payload: ['message' => __('node:record-publication')]);
		}
	}

	/**
	 * @template T
	 * @param callable(): T $work
	 * @return T
	 */
	private function transaction(callable $work, string $failure): mixed
	{
		try {
			return $this->db->transaction($work);
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
					$changes = $this->persist(
						$node,
						$prepared,
						$actor->id,
						$locales,
						create: true,
						metadata: $metadata,
					);
					[$created, $changed] = $changes['created'] ?? [null, null];
					$revision = $this->revise($changes['node'], null, $actor, $changed);
					$this->record(
						$node,
						$changes['node'],
						$prepared['uid'],
						$actor,
						[
							['action' => 'created', 'revision' => $revision],
							...array_map(
								static fn(array $action): array => [...$action, 'revision' => $revision],
								$changes['actions'],
							),
						],
						at: $created,
						stamp: $changed,
					);
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
				$nodeId = (int) $this->db->nodes->id(['uid' => $deleteUid])->one()['node'];
				$this->db->nodes->delete([
					'uid' => $deleteUid,
					'editor' => $actor->id,
				])->run();
				$this->record($node, $nodeId, $deleteUid, $actor, [['action' => 'deleted']]);
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
	 * Writes what differs from the stored node: whether the main version
	 * changed (content, title, handle or a URL path), and the actions of the
	 * other changes, a move and the publication.
	 *
	 * @param array{creator?: int, created?: ?string, changed?: ?string} $metadata
	 * @param array<mixed> $data
	 * @return array{node: int, revision: ?int, created: ?array{string, string}, edited: bool, actions: list<Action>}
	 */
	private function persist(
		object $node,
		array $data,
		int $editor,
		Locales $locales,
		bool $create = false,
		array $metadata = [],
	): array {
		$this->assertPublication($node, (bool) ($data['published'] ?? false), (bool) ($data['hidden'] ?? false));
		$parentUid = $this->resolveParentUid($node, $data);
		$parentId = $this->resolveParentId($parentUid);
		$handle = $this->resolveHandle($data);

		// Materialize the title alongside the content so both ride one UPDATE.
		$data['title'] = $this->materializeTitle($node, $data, $locales);
		$revision = $create ? null : $this->mainRevision($this->nodeId($node));

		[$nodeId, $rowChanged, $left, $created] = $this->persistNode($node, $data, $parentId, $create, $metadata);
		$handleChanged = $this->persistHandle($nodeId, $handle, $editor);
		$actions = $left === false ? [] : [['action' => 'moved', 'data' => ['from' => $left, 'to' => $parentUid]]];

		if ($this->hasPublication($node)) {
			$actions = [
				...$actions,
				...$this->persistPublication(
					$nodeId,
					(bool) ($data['published'] ?? false),
					(bool) ($data['hidden'] ?? false),
					$revision,
				),
			];
		}

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

		return [
			'node' => $nodeId,
			'revision' => $revision,
			'created' => $created,
			'edited' => $rowChanged || $handleChanged || $pathsChanged,
			'actions' => $actions,
		];
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
	 * The node id, whether the content or title changed, the uid of the
	 * parent the node left, null for the top level, or false when it
	 * stayed, and the creation and change time of a new node.
	 *
	 * @param array{creator?: int, created?: ?string, changed?: ?string} $metadata
	 * @param array<mixed> $data
	 * @return array{int, bool, string|null|false, ?array{string, string}}
	 */
	private function persistNode(
		object $node,
		array $data,
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
			'locked' => $data['locked'],
			'type' => $handle,
			'content' => json_encode($data['content']),
			'title' => json_encode($data['title'] ?? []),
		];

		if (!$create) {
			$nodeId = $this->nodeId($node);
			$saved = $this->db
				->nodes
				->save([
					'node' => $nodeId,
					'parent' => $params['parent'],
					'locked' => $params['locked'],
					'content' => $params['content'],
					'title' => $params['title'],
				])
				->first();

			if ($saved === null) {
				return [$nodeId, false, false, null];
			}

			$left = is_string($saved['left']) ? $saved['left'] : null;

			return [$nodeId, (bool) $saved['edited'], (bool) $saved['moved'] ? $left : false, null];
		}

		$result = $this->db
			->nodes
			->create([
				...$params,
				'creator' => $metadata['creator'] ?? throw new RuntimeException('A new node needs its creator'),
				'created' => $metadata['created'] ?? null,
				'changed' => $metadata['changed'] ?? null,
			])
			->first();

		if (!$result) {
			throw new HttpConflict(payload: [
				'message' => __('node:duplicate-uid', ['uid' => $data['uid']]),
			]);
		}

		return [(int) $result['node'], true, false, [(string) $result['created'], (string) $result['changed']]];
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
