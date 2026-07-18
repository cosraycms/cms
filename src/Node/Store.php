<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Core\Exception\HttpBadRequest;
use Celema\Core\Exception\HttpConflict;
use Celema\Core\Exception\HttpError;
use Celema\Core\Request;
use Celema\Quma\Database;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RoutePathError;
use Cosray\Exception\RuntimeException;
use Cosray\Locale;
use Cosray\Locales;
use Cosray\Node\Contract\Title as TitleContract;
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
	}

	public function save(
		object $node,
		array $data,
		Request $request,
		Locales $locales,
		bool $create = false,
	): array {
		$data = $this->normalizeSubmittedHandle($data);
		$data = $this->validate($node, $data, $locales, $request);

		if (is_array($data['content'] ?? null)) {
			// Richtext documents persist in canonical form (byte-stable
			// storage for history diffs); empty documents become null.
			$data['content'] = new Normalizer()->content($data['content']);
		}

		if (!$create) {
			$this->assertUidUnchanged($node, $data, $request);
		}

		$data = $this->completeHandle($node, $data);

		if ($data['locked']) {
			throw new HttpBadRequest($request, payload: ['message' => __('node:locked')]);
		}

		try {
			$editor = $request->get('session')->authenticatedUserId();

			if (!$editor) {
				$editor = 1;
			}
		} catch (Throwable) {
			$editor = 1;
		}

		try {
			$this->db->begin();

			$this->persist($node, $data, $editor, $locales, $request, $create);

			$this->db->commit();
		} catch (Throwable $e) {
			$this->db->rollback();

			if ($e instanceof HttpError) {
				throw $e;
			}

			throw new RuntimeException(
				'Error while saving: ' . $e->getMessage(),
				(int) $e->getCode(),
				previous: $e,
			);
		}

		return [
			'success' => true,
			'uid' => $data['uid'],
		];
	}

	public function create(object $node, array $data, Request $request, Locales $locales): array
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
				return $this->save($node, $data, $request, $locales, create: true);
			} catch (HttpConflict $e) {
				if (!$generatedUid || $attempt === $attempts) {
					throw $e;
				}
			}
		}

		throw new RuntimeException('Could not generate a unique node uid');
	}

	public function delete(object $node, Request $request, bool $requireJson = true): array
	{
		if ($requireJson && $request->header('Accept') !== 'application/json') {
			throw new HttpBadRequest($request);
		}

		$uid = Factory::meta($node, 'uid');

		$this->db->nodes->delete([
			'uid' => $uid,
			'editor' => $request->get('session')->authenticatedUserId(),
		])->run();

		return [
			'success' => true,
			'error' => false,
		];
	}

	public function validate(object $node, array $data, Locales $locales, Request $request): array
	{
		$factory = new ValidatorFactory($node, $locales);
		$shape = $factory->create();
		$result = $shape->validate($data);

		if (!$result->valid()) {
			throw new HttpBadRequest($request, payload: [
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
		Request $request,
		bool $create = false,
	): void {
		$parentUid = $this->resolveParentUid($node, $data, $request);
		$parentId = $this->resolveParentId($parentUid, $request);
		$handle = $this->resolveHandle($data);

		// Materialize the title alongside the content so both ride one UPDATE
		// (a single change/history record instead of two).
		$data['title'] = $this->materializeTitle($node, $data, $request, $locales);

		$nodeId = $this->persistNode($node, $data, $editor, $parentId, $create, $request);
		$this->persistHandle($nodeId, $handle, $editor, $request);

		// The reference indexes ride in the save transaction: full
		// replace per owner from the content just written.
		$this->sync->replace('node', $data['uid'], $this->scanner->scan($data['content'] ?? []));

		if ((bool) $this->types->get($node::class, 'routable', false)) {
			$this->ensureRouteHandle($node, $handle, $request);
			$data = $this->completeGeneratedPaths($node, $data, $locales, $parentId, $request);
			$this->pathManager->persist($this->db, $data, $editor, $nodeId, $locales);
		}
	}

	/**
	 * The materialized title map for the content being saved. Mirrors
	 * `Node::title()`'s resolution but yields every locale.
	 *
	 * @return array<string, string>
	 */
	private function materializeTitle(
		object $node,
		array $data,
		Request $request,
		Locales $locales,
	): array {
		$content = is_array($data['content'] ?? null) ? $data['content'] : [];
		$descriptor = $this->titleResolver->descriptor($node::class);

		return match ($descriptor['kind']) {
			TitleResolver::KIND_FIELD => $this->titleResolver->fieldMap($content, $descriptor['field']),
			TitleResolver::KIND_DYNAMIC => $this->dynamicTitle($node, $data, $request, $locales),
			default => [],
		};
	}

	/**
	 * Evaluate a dynamic title once per locale against the content being
	 * saved, restoring the request locale afterwards.
	 *
	 * @return array<string, string>
	 */
	private function dynamicTitle(object $node, array $data, Request $request, Locales $locales): array
	{
		$target = $this->titleEvalNode($node, $data);

		if (!$target instanceof TitleContract) {
			return [];
		}

		$original = $request->get('locale', null);

		try {
			return $this->titleResolver->dynamicMap(
				static function (Locale $locale) use ($target, $request): string {
					$request->set('locale', $locale);

					return $target->title();
				},
				$locales,
			);
		} finally {
			$request->set('locale', $original);
		}
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
		Request $request,
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
			$nodeId = Factory::meta($node, 'node');

			if (!is_int($nodeId) && !is_string($nodeId)) {
				throw new RuntimeException('Missing node id for update');
			}

			return (int) $this->db->nodes->save([
				'node' => (int) $nodeId,
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
			throw new HttpConflict($request, payload: [
				'message' => __('node:duplicate-uid', ['uid' => $data['uid']]),
			]);
		}

		return (int) $result['node'];
	}

	private function persistHandle(int $nodeId, ?string $handle, int $editor, Request $request): void
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
			throw new HttpConflict($request, payload: [
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
				throw new HttpConflict($request, payload: [
					'message' => __('node:duplicate-handle', ['handle' => $handle]),
				]);
			}

			throw $e;
		}
	}

	private function resolveParentUid(object $node, array $data, Request $request): ?string
	{
		$parentUid = array_key_exists('parent', $data)
			? $data['parent']
			: Factory::meta($node, 'parent');

		if ($parentUid === null) {
			return null;
		}

		if (!is_string($parentUid)) {
			throw new HttpBadRequest($request, payload: [
				'message' => __('node:parent-not-string'),
			]);
		}

		$parentUid = trim($parentUid);

		if ($parentUid === '') {
			return null;
		}

		return $parentUid;
	}

	private function resolveParentId(?string $parentUid, Request $request): ?int
	{
		if ($parentUid === null) {
			return null;
		}

		$parent = $this->db
			->nodes
			->parentIdByUid(['uid' => $parentUid])
			->first();

		if (!$parent) {
			throw new HttpBadRequest($request, payload: [
				'message' => __('node:invalid-parent', ['uid' => $parentUid]),
			]);
		}

		return (int) $parent['node'];
	}

	private function assertUidUnchanged(object $node, array $data, Request $request): void
	{
		$uid = Factory::meta($node, 'uid');

		if (!is_string($uid) || $data['uid'] === $uid) {
			return;
		}

		throw new HttpBadRequest($request, payload: [
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

	private function ensureRouteHandle(object $node, ?string $handle, Request $request): void
	{
		$route = $this->types->get($node::class, 'route');

		if (!$this->routeNeedsHandle($route) || $handle !== null) {
			return;
		}

		throw new HttpBadRequest($request, payload: [
			'message' => __('node:handle-required'),
		]);
	}

	private function completeGeneratedPaths(
		object $node,
		array $data,
		Locales $locales,
		?int $parentId,
		Request $request,
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
				$request,
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
