<?php

declare(strict_types=1);

namespace Celemas\Cms\Node;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Locales;
use Celemas\Cms\Validation\ValidatorFactory;
use Celemas\Core\Exception\HttpBadRequest;
use Celemas\Core\Request;
use Celemas\Quma\Database;
use Throwable;

class Store
{
	public function __construct(
		private readonly Database $db,
		private readonly PathManager $pathManager,
		private readonly Types $types,
	) {}

	public function save(object $node, array $data, Request $request, Locales $locales): array
	{
		$data = $this->validate($node, $data, $locales, $request);

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

			$this->persist($node, $data, $editor, $locales, $request);

			$this->db->commit();
		} catch (Throwable $e) {
			$this->db->rollback();

			if ($e instanceof HttpBadRequest) {
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
		$existing = $this->db->nodes->find(['uid' => $data['uid']])->first();

		if ($existing) {
			throw new HttpBadRequest($request, payload: [
				'message' => _('A node with the same uid already exists: ') . $data['uid'],
			]);
		}

		return $this->save($node, $data, $request, $locales);
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
	): void {
		$parentUid = $this->resolveParentUid($node, $data, $request);
		$parentId = $this->resolveParentId($parentUid, $request);

		$nodeId = $this->persistNode($node, $data, $editor, $parentId);

		if ((bool) $this->types->get($node::class, 'routable', false)) {
			$this->pathManager->persist($this->db, $data, $editor, $nodeId, $locales);
		}
	}

	private function persistNode(object $node, array $data, int $editor, ?int $parent): int
	{
		$class = $node::class;
		$handle = (string) $this->types->get($class, 'handle');
		$this->ensureTypeExists($handle);

		return (int) $this->db->nodes->save([
			'uid' => $data['uid'],
			'parent' => $parent,
			'hidden' => $data['hidden'],
			'published' => $data['published'],
			'locked' => $data['locked'],
			'type' => $handle,
			'content' => json_encode($data['content']),
			'editor' => $editor,
		])->one()['node'];
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
