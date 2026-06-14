<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celemas\Core\Exception\HttpBadRequest;
use Celemas\Core\Exception\HttpConflict;
use Celemas\Core\Exception\HttpError;
use Celemas\Core\Request;
use Celemas\Quma\Database;
use Cosray\Exception\RuntimeException;
use Cosray\Locales;
use Cosray\Uid;
use Cosray\Validation\ValidatorFactory;
use Throwable;

class Store
{
	private const int CREATE_UID_ATTEMPTS = 5;

	public function __construct(
		private readonly Database $db,
		private readonly PathManager $pathManager,
		private readonly Types $types,
		private readonly Uid $uid,
	) {}

	public function save(
		object $node,
		array $data,
		Request $request,
		Locales $locales,
		bool $create = false,
	): array {
		$data = $this->normalizeSubmittedHandle($data);
		$data = $this->validate($node, $data, $locales, $request);
		$data = $this->completeHandle($node, $data);

		if ($data['locked']) {
			throw new HttpBadRequest($request, payload: ['message' => _('This document is locked')]);
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
				_('Fehler beim Speichern: ') . $e->getMessage(),
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

		throw new RuntimeException(_('Could not generate a unique node uid'));
	}

	public function delete(object $node, Request $request): array
	{
		if ($request->header('Accept') !== 'application/json') {
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
				'message' => _('Incomplete or invalid data'),
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

		$nodeId = $this->persistNode($node, $data, $editor, $parentId, $create, $request);
		$this->persistHandle($nodeId, $handle, $editor, $request);

		if ((bool) $this->types->get($node::class, 'routable', false)) {
			$this->ensureRouteHandle($node, $handle, $request);
			$this->pathManager->persist($this->db, $data, $editor, $nodeId, $locales);
		}
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
			'editor' => $editor,
		];

		if (!$create) {
			$nodeId = Factory::meta($node, 'node');

			if (!is_int($nodeId) && !is_string($nodeId)) {
				throw new RuntimeException(_('Missing node id for update'));
			}

			return (int) $this->db->nodes->save([
				'node' => (int) $nodeId,
				'parent' => $params['parent'],
				'hidden' => $params['hidden'],
				'published' => $params['published'],
				'locked' => $params['locked'],
				'content' => $params['content'],
				'editor' => $params['editor'],
			])->one()['node'];
		}

		$result = $this->db->nodes->create($params)->first();

		if (!$result) {
			throw new HttpConflict($request, payload: [
				'message' => _('A node with the same uid already exists: ') . $data['uid'],
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
				'message' => _('A node uid with the same handle already exists: ') . $handle,
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
					'message' => _('A node with the same handle already exists: ') . $handle,
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
				'message' => _('Parent must be a uid string'),
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
			->execute(
				'SELECT node FROM cms.nodes WHERE uid = :uid AND deleted IS NULL LIMIT 1',
				['uid' => $parentUid],
			)
			->first();

		if (!$parent) {
			throw new HttpBadRequest($request, payload: [
				'message' => _('Invalid parent uid: ') . $parentUid,
			]);
		}

		return (int) $parent['node'];
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
			'message' => _('A handle is required for this node route'),
		]);
	}

	private function routeNeedsHandle(mixed $route): bool
	{
		if (is_string($route)) {
			return str_contains($route, '{handle}');
		}

		if (!is_array($route)) {
			return false;
		}

		foreach ($route as $localizedRoute) {
			if (is_string($localizedRoute) && str_contains($localizedRoute, '{handle}')) {
				return true;
			}
		}

		return false;
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
