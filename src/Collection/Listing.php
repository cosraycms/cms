<?php

declare(strict_types=1);

namespace Cosray\Collection;

use Cosray\Collection;
use Cosray\Exception\RuntimeException;
use Cosray\Node\Node;
use Cosray\Node\Types;

/**
 * List, row and hierarchy logic for collection pages.
 *
 * Kept out of the Collection base class so collections stay pure
 * configuration + query objects.
 */
final class Listing
{
	public function __construct(
		private readonly Collection $collection,
		private readonly Types $types,
	) {}

	public function list(
		int $offset = 0,
		int $limit = 50,
		string $q = '',
		string $sort = '',
		string $dir = 'desc',
		?string $parent = null,
	): array {
		$nodes = $this->collection->entries();

		if ($this->collection->listMeta->showChildren) {
			$parent = trim((string) $parent);

			if ($parent === '') {
				$nodes->roots();
			} else {
				$nodes->childrenOf($parent);
			}
		}

		$q = trim($q);

		if ($q !== '') {
			$nodes->search($q, $this->collection->searchFields());
		}

		[$sort, $dir, $order] = $this->resolveOrder($sort, $dir);
		$nodes->order(...$order);

		$total = $nodes->count();
		$nodes->offset($offset)->limit($limit);
		$pageNodes = iterator_to_array($nodes);

		return [
			'total' => $total,
			'offset' => $offset,
			'limit' => $limit,
			'q' => $q,
			'sort' => $sort,
			'dir' => $dir,
			'nodes' => $this->rows($pageNodes),
		];
	}

	/** @return list<array{slug: string, name: string}> */
	public function childBlueprints(Node $node): array
	{
		$children = $node->meta->type->children;

		if (!is_array($children) || $children === []) {
			return [];
		}

		$result = [];

		foreach ($children as $class) {
			if (!is_string($class) || $class === '') {
				throw new RuntimeException('The children schema must contain non-empty class names');
			}

			if (!$this->types->isNode($class)) {
				throw new RuntimeException("Unknown child node class '{$class}' in #[Children(...)]");
			}

			$result[] = [
				'slug' => (string) $this->types->get($class, 'handle'),
				'name' => (string) $this->types->get($class, 'label'),
			];
		}

		return $result;
	}

	/**
	 * @param list<Node> $nodes
	 */
	private function rows(array $nodes): array
	{
		$result = [];
		$hasChildren = $this->collection->listMeta->showChildren
			? $this->hasChildrenMap($nodes)
			: [];

		foreach ($nodes as $node) {
			$result[] = $this->row($node, $hasChildren[$node->meta->uid] ?? false);
		}

		return $result;
	}

	private function row(Node $node, bool $hasChildren): array
	{
		$columns = [];
		$parent = $node->meta->get('parent');

		if (!is_string($parent) || trim($parent) === '') {
			$parent = null;
		}

		$childBlueprints = $this->collection->listMeta->showChildren
			? $this->childBlueprints($node)
			: [];

		foreach ($this->collection->columns() as $column) {
			$columns[] = $column->get($node);
		}

		return [
			'uid' => $node->meta->uid,
			'published' => $node->meta->published,
			'locked' => $node->meta->locked,
			'hidden' => $node->meta->hidden,
			'parent' => $parent,
			'hasChildren' => $hasChildren,
			'childBlueprints' => $childBlueprints,
			'columns' => $columns,
		];
	}

	/**
	 * @param list<Node> $nodes
	 * @return array<string, bool>
	 */
	private function hasChildrenMap(array $nodes): array
	{
		if ($nodes === []) {
			return [];
		}

		$uids = [];

		foreach ($nodes as $node) {
			$uids[] = $node->meta->uid;
		}

		$uids = array_values(array_unique($uids));
		$list = implode(',', array_map(
			static fn(string $uid): string => "'" . str_replace("'", "\\\\'", $uid) . "'",
			$uids,
		));

		if ($list === '') {
			return [];
		}

		$children = $this->collection
			->cms
			->nodes("parent @ [{$list}]")
			->published(null)
			->hidden(null);
		$result = [];

		foreach ($children as $child) {
			$parentUid = $child->meta->get('parent');

			if (is_string($parentUid) && $parentUid !== '') {
				$result[$parentUid] = true;
			}
		}

		return $result;
	}

	private function resolveOrder(string $sort, string $dir): array
	{
		$sort = trim($sort);
		$dir = strtolower(trim($dir));
		$dir = in_array($dir, ['asc', 'desc'], true) ? $dir : strtolower($this->collection->defaultDir());
		$sorts = $this->collection->sorts();
		$sort = array_key_exists($sort, $sorts) ? $sort : $this->collection->defaultSort();
		$field = $sorts[$sort] ?? 'changed';
		$order = [sprintf('%s %s', $field, strtoupper($dir))];

		if ($field !== 'uid') {
			$order[] = 'uid ASC';
		}

		return [$sort, $dir, $order];
	}
}
