<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Core\Exception\HttpBadRequest;
use Celema\Core\Exception\HttpConflict;
use Celema\Core\Exception\HttpError;
use Celema\Quma\Database;
use Cosray\Actor;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RoutePathError;
use Cosray\Exception\RuntimeException;
use Cosray\Locale;
use Cosray\Locales;
use Cosray\References;
use Cosray\Richtext\Normalizer;
use Cosray\Title\Resolver as TitleResolver;
use Cosray\Uid;
use Cosray\Validation\ValidatorFactory;
use Throwable;

class Store
{
	private const int CREATE_UID_ATTEMPTS = 5;

	private readonly RoutePathGenerator $routePathGenerator;
	private readonly References\Scanner $scanner;
	private readonly References\Sync $sync;
	private readonly TitleResolver $titleResolver;
	private readonly Drafts $drafts;

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
	}

	/**
	 * Writes the live row. A pending working copy is left alone: this is
	 * the API path, and the editor's publish() is what supersedes a draft.
	 *
	 * @return array{success: true, uid: string}
	 */
	public function save(
		object $node,
		array $data,
		Locales $locales,
		Actor $actor,
		bool $create = false,
	): array {
		$data = $this->prepare($node, $data, $locales, $create);

		$this->transaction(
			fn() => $this->persist($node, $data, $actor->id, $locales, $create),
			'Error while saving: ',
		);

		return ['success' => true, 'uid' => $data['uid']];
	}

	/**
	 * Writes the live row and drops the working copy it supersedes.
	 *
	 * @return array{success: true, uid: string}
	 */
	public function publish(object $node, array $data, Locales $locales, Actor $actor): array
	{
		$data = $this->prepare($node, $data, $locales);
		$nodeId = $this->nodeId($node);

		$this->transaction(function () use ($node, $data, $locales, $actor, $nodeId): void {
			$this->persist($node, $data, $actor->id, $locales);
			$this->drafts->delete($nodeId);
			$this->sync->remove('draft', $data['uid']);
		}, 'Error while publishing: ');

		return ['success' => true, 'uid' => $data['uid']];
	}

	/**
	 * Saves the working copy of a published node. Content, handle and
	 * paths wait for publish(); `hidden` is a live switch and applies now.
	 *
	 * @return array{success: true, uid: string}
	 */
	public function draft(object $node, array $data, Locales $locales, Actor $actor): array
	{
		if (!$this->holdsDrafts($node)) {
			throw new RuntimeException('Only a published, renderable node can hold a working copy');
		}

		$data = $this->prepare($node, $data, $locales);
		$nodeId = $this->nodeId($node);
		$content = is_array($data['content'] ?? null) ? $data['content'] : [];

		$this->transaction(function () use ($node, $data, $actor, $nodeId, $content): void {
			$this->drafts->save(
				$nodeId,
				$content,
				['handle' => $data['handle'], 'paths' => $this->submittedPaths($data)],
				$actor->id,
			);
			$this->sync->replace('draft', $data['uid'], $this->scanner->scan($content));

			if ($data['hidden'] !== (bool) Factory::meta($node, 'hidden')) {
				$this->db->nodes->setHidden([
					'node' => $nodeId,
					'hidden' => $data['hidden'],
					'editor' => $actor->id,
				])->run();
			}
		}, 'Error while saving the working copy: ');

		return ['success' => true, 'uid' => $data['uid']];
	}

	public function discard(object $node): void
	{
		$nodeId = $this->nodeId($node);
		$uid = (string) Factory::meta($node, 'uid');

		$this->transaction(function () use ($nodeId, $uid): void {
			$this->drafts->delete($nodeId);
			$this->sync->remove('draft', $uid);
		}, 'Error while discarding: ');
	}

	/**
	 * Takes the node offline. A working copy becomes the node content:
	 * with nothing live left to protect, the node is edited directly again.
	 */
	public function unpublish(object $node, Locales $locales, Actor $actor): void
	{
		$draft = $this->drafts->get($this->nodeId($node));

		if ($draft === null) {
			$this->db->nodes->setPublished([
				'uid' => (string) Factory::meta($node, 'uid'),
				'published' => false,
				'editor' => $actor->id,
			])->run();

			return;
		}

		$this->publish($node, $this->workingData($node, $draft, published: false), $locales, $actor);
	}

	/** Publishes the working copy, if there is one. */
	public function publishDraft(object $node, Locales $locales, Actor $actor): bool
	{
		$draft = $this->drafts->get($this->nodeId($node));

		if ($draft === null) {
			return false;
		}

		$this->publish($node, $this->workingData($node, $draft, published: true), $locales, $actor);

		return true;
	}

	public function holdsDrafts(object $node): bool
	{
		return (
			(bool) $this->types->get($node::class, 'renderable', false)
				&& (bool) Factory::meta($node, 'published')
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

	/** @return array<string, string> */
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

	private function transaction(callable $work, string $failure): void
	{
		$ownsTransaction = !$this->db->getConn()->inTransaction();

		try {
			if ($ownsTransaction) {
				$this->db->begin();
			}

			$work();

			if ($ownsTransaction) {
				$this->db->commit();
			}
		} catch (Throwable $e) {
			if ($ownsTransaction) {
				$this->db->rollback();
			}

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

	public function create(object $node, array $data, Locales $locales, Actor $actor): array
	{
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
				return $this->save($node, $data, $locales, $actor, create: true);
			} catch (HttpConflict $e) {
				if (!$generatedUid || $attempt === $attempts) {
					throw $e;
				}
			}
		}

		throw new RuntimeException('Could not generate a unique node uid');
	}

	/** @return array{success: true, error: false, deleted: list<string>} */
	public function delete(object $node, Actor $actor, bool $withChildren = false): array
	{
		$uid = (string) Factory::meta($node, 'uid');

		if (!$withChildren && $this->childUids($uid) !== []) {
			throw new HttpConflict(payload: ['message' => __('node:has-children')]);
		}

		$uids = $withChildren ? $this->subtreeUids($uid) : [$uid];

		$this->transaction(function () use ($uids, $actor): void {
			foreach ($uids as $deleteUid) {
				$this->db->nodes->delete([
					'uid' => $deleteUid,
					'editor' => $actor->id,
				])->run();
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

			foreach ($this->childUids($current) as $child) {
				// A parent cycle would loop forever; unseen uids only.
				if (in_array($child, $uids, true)) {
					continue;
				}

				$uids[] = $child;
				$queue[] = $child;
			}
		}

		return array_reverse($uids);
	}

	/** @return list<string> */
	private function childUids(string $uid): array
	{
		return array_map(
			static fn(array $row): string => (string) $row['uid'],
			$this->db->nodes->childUids(['uid' => $uid])->all(),
		);
	}

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

	private function persist(
		object $node,
		array $data,
		int $editor,
		Locales $locales,
		bool $create = false,
	): void {
		$parentUid = $this->resolveParentUid($node, $data);
		$parentId = $this->resolveParentId($parentUid);
		$handle = $this->resolveHandle($data);

		// Materialize the title alongside the content so both ride one UPDATE
		// (a single change/history record instead of two).
		$data['title'] = $this->materializeTitle($node, $data, $locales);

		$nodeId = $this->persistNode($node, $data, $editor, $parentId, $create);
		$this->persistHandle($nodeId, $handle, $editor);

		// The reference indexes ride in the save transaction: full
		// replace per owner from the content just written.
		$this->sync->replace('node', $data['uid'], $this->scanner->scan($data['content'] ?? []));

		if ((bool) $this->types->get($node::class, 'routable', false)) {
			$this->ensureRouteHandle($node, $handle);
			$data = $this->completeGeneratedPaths($node, $data, $locales, $parentId);
			$this->pathManager->persist($this->db, $data, $editor, $nodeId, $locales);
		}
	}

	/**
	 * The materialized title map for the content being saved. Mirrors
	 * `Wrapper::title()`'s resolution but yields every locale.
	 *
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

	private function persistNode(
		object $node,
		array $data,
		int $editor,
		?int $parent,
		bool $create,
	): int {
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
			return (int) $this->db->nodes->save([
				'node' => $this->nodeId($node),
				'parent' => $params['parent'],
				'hidden' => $params['hidden'],
				'published' => $params['published'],
				'locked' => $params['locked'],
				'content' => $params['content'],
				'title' => $params['title'],
				'editor' => $params['editor'],
			])->one()['node'];
		}

		$result = $this->db->nodes->create($params)->first();

		if (!$result) {
			throw new HttpConflict(payload: [
				'message' => __('node:duplicate-uid', ['uid' => $data['uid']]),
			]);
		}

		return (int) $result['node'];
	}

	private function persistHandle(int $nodeId, ?string $handle, int $editor): void
	{
		if ($handle === null) {
			$this->db->nodes->deleteHandle(['node' => $nodeId])->run();

			return;
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
			$this->db->nodes->saveHandle([
				'node' => $nodeId,
				'handle' => $handle,
				'editor' => $editor,
			])->run();
		} catch (Throwable $e) {
			if ((string) $e->getCode() === '23505') {
				throw new HttpConflict(payload: [
					'message' => __('node:duplicate-handle', ['handle' => $handle]),
				]);
			}

			throw $e;
		}
	}

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

	private function normalizeSubmittedHandle(array $data): array
	{
		if (!array_key_exists('handle', $data) || !is_string($data['handle'])) {
			return $data;
		}

		$handle = trim($data['handle']);
		$data['handle'] = $handle === '' ? null : $handle;

		return $data;
	}

	private function completeHandle(object $node, array $data): array
	{
		if (!array_key_exists('handle', $data)) {
			$data['handle'] = Factory::meta($node, 'handle');
		}

		$data['handle'] = $this->resolveHandle($data);

		return $data;
	}

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
