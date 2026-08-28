<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Core\Exception\HttpBadRequest;
use Celema\Core\Exception\HttpConflict;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Core\Response;
use Celema\Wire\Creator;
use Cosray\Actor;
use Cosray\Cms;
use Cosray\Collection as CmsCollection;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Navigation;
use Cosray\Node\PathManager;
use Cosray\Node\Store;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
use Throwable;

/**
 * Bulk operations on a collection listing selection. Every action runs in
 * one transaction, skips nodes it must not touch, and redirects back to
 * the listing with a `notice` summary the collection page renders.
 */
final class Bulk extends Panel
{
	private const int MAX_NODES = 250;

	public function publish(Context $context, Factory $factory, string $collection): Response
	{
		$obj = $this->collection($collection);
		$form = $this->formData();
		$state = $form['state'] ?? null;

		if (!in_array($state, ['published', 'draft'], true)) {
			throw new HttpBadRequest($this->request);
		}

		$published = $state === 'published';
		[$nodes, $missing] = $this->selection($obj, $form);
		$editor = $this->actor()->id;
		$changed = 0;
		$skippedLocked = 0;

		$this->transaction($context, static function () use (
			$context,
			$nodes,
			$published,
			$editor,
			&$changed,
			&$skippedLocked,
		): void {
			foreach ($nodes as $uid => $node) {
				if ($node->meta->locked) {
					$skippedLocked++;

					continue;
				}

				$context
					->db
					->nodes
					->setPublished([
						'uid' => $uid,
						'published' => $published,
						'editor' => $editor,
					])
					->run();
				$changed++;
			}
		});

		return $this->redirect($factory, $collection, [
			$published ? 'published' : 'drafted' => $changed,
			'skipped-locked' => $skippedLocked,
			'skipped' => $missing,
		]);
	}

	public function delete(Context $context, Cms $cms, Factory $factory, string $collection): Response
	{
		$obj = $this->collection($collection);
		$form = $this->formData();
		$withChildren = ($form['children'] ?? null) === '1';
		[$nodes, $missing] = $this->selection($obj, $form);
		$store = new Store(
			$context->db,
			new PathManager(),
			$this->types(),
			$cms->nodeFactory()->uid(),
			factory: $cms->nodeFactory(),
			cms: $cms,
			context: $context,
		);
		$actor = $this->actor();
		$deleted = [];
		$skippedChildren = 0;
		$skippedLocked = 0;
		$skipped = $missing;

		$this->transaction($context, function () use (
			$store,
			$actor,
			$nodes,
			$withChildren,
			&$deleted,
			&$skippedChildren,
			&$skippedLocked,
			&$skipped,
		): void {
			foreach ($this->childrenFirst($nodes) as $uid => $node) {
				// Already gone with an earlier subtree delete.
				if (in_array($uid, $deleted, true)) {
					continue;
				}

				if ($node->meta->locked) {
					$skippedLocked++;

					continue;
				}

				$nodeObj = Wrapper::unwrap($node);

				if (!(bool) $this->types()->get($nodeObj::class, 'deletable', true)) {
					$skipped++;

					continue;
				}

				try {
					$result = $store->delete($nodeObj, $actor, $withChildren);
					$deleted = [...$deleted, ...$result['deleted']];
				} catch (HttpConflict) {
					// The guard refuses before any SQL runs, so the
					// transaction stays healthy and the batch goes on.
					$skippedChildren++;
				}
			}
		});

		return $this->redirect($factory, $collection, [
			'deleted' => count($deleted),
			'skipped-children' => $skippedChildren,
			'skipped-locked' => $skippedLocked,
			'skipped' => $skipped,
		]);
	}

	private function transaction(Context $context, callable $work): void
	{
		$db = $context->db;
		$ownsTransaction = !$db->getConn()->inTransaction();

		if ($ownsTransaction) {
			$db->begin();
		}

		try {
			$work();

			if ($ownsTransaction) {
				$db->commit();
			}
		} catch (Throwable $e) {
			if ($ownsTransaction) {
				$db->rollback();
			}

			throw $e;
		}
	}

	/**
	 * The submitted uids resolved through the collection's own finder, so
	 * only nodes the listing actually shows are operable. Returns the
	 * found wrappers keyed by uid plus the count of uids that did not
	 * resolve (unknown, deleted, or outside the collection).
	 *
	 * @param array<array-key, mixed> $form
	 * @return array{0: array<string, Wrapper>, 1: int}
	 */
	private function selection(CmsCollection $obj, array $form): array
	{
		$submitted = $form['nodes'] ?? null;

		if (!is_array($submitted)) {
			throw new HttpBadRequest($this->request);
		}

		$uids = [];

		foreach ($submitted as $uid) {
			if (!is_string($uid)) {
				throw new HttpBadRequest($this->request);
			}

			$uid = trim($uid);

			if ($uid !== '' && !in_array($uid, $uids, true)) {
				$uids[] = $uid;
			}
		}

		if ($uids === [] || count($uids) > self::MAX_NODES) {
			throw new HttpBadRequest($this->request);
		}

		$nodes = [];

		foreach ($obj->entries()->only(...$uids) as $node) {
			$nodes[$node->meta->uid] = $node;
		}

		return [$nodes, count($uids) - count($nodes)];
	}

	/**
	 * Selected children before selected parents, so deleting a branch that
	 * was selected row by row needs no subtree flag and no second attempt.
	 *
	 * @param array<string, Wrapper> $nodes
	 * @return array<string, Wrapper>
	 */
	private function childrenFirst(array $nodes): array
	{
		$depths = [];

		foreach ($nodes as $uid => $node) {
			$depth = 0;
			$current = $node;

			while ($depth <= count($nodes)) {
				$parent = $current->meta->get('parent');

				if (!is_string($parent) || !isset($nodes[$parent])) {
					break;
				}

				$current = $nodes[$parent];
				$depth++;
			}

			$depths[$uid] = $depth;
		}

		uksort(
			$nodes,
			static fn(string $a, string $b): int => $depths[$b] <=> $depths[$a],
		);

		return $nodes;
	}

	/** @param array<string, int> $counts */
	private function redirect(Factory $factory, string $collection, array $counts): Response
	{
		$notice = [];

		foreach ($counts as $key => $count) {
			if ($count > 0) {
				$notice[] = $key . ':' . $count;
			}
		}

		// Reflect the listing query the bulk URL carried back into the
		// redirect, so the user lands on the view they acted in.
		$params = [];

		foreach (['q', 'sort', 'dir', 'offset', 'limit', 'parent', 'view', 'open'] as $key) {
			$value = $this->request->param($key, '');

			if (is_string($value) && trim($value) !== '') {
				$params[$key] = trim($value);
			}
		}

		if ($notice !== []) {
			$params['notice'] = implode(',', $notice);
		}

		$path = $this->panelPath() . '/collection/' . rawurlencode($collection);
		$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

		return Response::create($factory)->redirect(
			$query === '' ? $path : $path . '?' . $query,
			303,
		);
	}

	private function collection(string $collection): CmsCollection
	{
		try {
			$ref = $this->navigation()->ref($collection);
		} catch (RuntimeException $e) {
			throw new HttpNotFound($this->request, previous: $e);
		}

		$creator = new Creator($this->container);
		$obj = $creator->create(
			$ref->class,
			predefinedTypes: [Request::class => $this->request],
		);
		assert($obj instanceof CmsCollection, 'The bulk route must resolve a collection');

		return $obj;
	}

	private function actor(): Actor
	{
		try {
			$id = $this->request->get('session')->authenticatedUserId();
		} catch (Throwable) {
			$id = null;
		}

		return $id ? new Actor((int) $id) : Actor::system();
	}

	private function types(): Types
	{
		$types = $this->container->get(Types::class);
		assert($types instanceof Types, 'The node type service must be available');

		return $types;
	}

	private function navigation(): Navigation
	{
		$navigation = $this->container->get(Navigation::class);
		assert($navigation instanceof Navigation, 'The navigation service must be available');

		return $navigation;
	}
}
