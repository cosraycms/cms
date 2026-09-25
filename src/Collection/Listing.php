<?php

declare(strict_types=1);

namespace Cosray\Collection;

use Cosray\Cms;
use Cosray\Collection;
use Cosray\CollectionListMeta;
use Cosray\Column;
use Cosray\Exception\RuntimeException;
use Cosray\Finder\Nodes;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;

/**
 * A registered collection as the panel lists it: its entries, columns,
 * sorts, list options and blueprints, plus the row and hierarchy logic
 * of collection pages.
 *
 * Kept out of the collection class so collections stay pure
 * configuration + query objects.
 */
final class Listing
{
	/** @var list<Column> */
	public readonly array $columns;
	/** @var array<string, Sort> */
	public readonly array $sorts;
	public readonly CollectionListMeta $meta;
	/** @var list<class-string> */
	public readonly array $blueprints;
	public readonly string $defaultSort;

	public function __construct(
		private readonly Collection $collection,
		private readonly Cms $cms,
		private readonly Types $types,
	) {
		$this->meta = $collection->listMeta;
		$this->blueprints = $collection->blueprints();
		$this->columns = array_values($collection->columns());
		$sorts = [];
		foreach ($this->columns as $column) {
			if (!$column instanceof Column) {
				throw new RuntimeException('Collection columns must be Column objects');
			}
			$sort = $column->sort;
			if ($sort === null) {
				continue;
			}
			if (isset($sorts[$sort->key])) {
				throw new RuntimeException("Duplicate collection sort key '{$sort->key}'");
			}
			$sorts[$sort->key] = $sort;
		}
		$this->sorts = $sorts;
		$this->defaultSort = self::defaultSort($sorts);
	}

	/** A fresh finder over the collection's entries per call; callers narrow it. */
	public function entries(): Nodes
	{
		return $this->collection->entries();
	}

	/** The node a hierarchy listing is scoped to, in any state. */
	public function parent(string $uid): ?Wrapper
	{
		return $this->cms->node->byUid($uid, published: null);
	}

	public function list(
		int $offset = 0,
		int $limit = 50,
		string $q = '',
		string $sort = '',
		string $dir = '',
		?string $parent = null,
	): array {
		[$sort, $dir, $order] = $this->resolveOrder($sort, $dir);
		$nodes = $this->entries();

		if ($this->meta->showChildren) {
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
	public function childBlueprints(Wrapper $node): array
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
	 * @param list<Wrapper> $nodes
	 */
	private function rows(array $nodes): array
	{
		$result = [];
		$hasChildren = $this->meta->showChildren
			? $this->hasChildrenMap($nodes)
			: [];

		foreach ($nodes as $node) {
			$result[] = $this->row($node, $hasChildren[$node->meta->uid] ?? false);
		}

		return $result;
	}

	private function row(Wrapper $node, bool $hasChildren): array
	{
		$columns = [];
		$parent = $node->meta->get('parent');

		if (!is_string($parent) || trim($parent) === '') {
			$parent = null;
		}

		$childBlueprints = $this->meta->showChildren
			? $this->childBlueprints($node)
			: [];

		foreach ($this->columns as $column) {
			$columns[] = $column->get($node);
		}

		return [
			'uid' => $node->meta->uid,
			'published' => $node->meta->published,
			'hasDraft' => $node->meta->get('draft') !== null,
			'locked' => $node->meta->locked,
			'hidden' => $node->meta->hidden,
			'parent' => $parent,
			'hasChildren' => $hasChildren,
			'childBlueprints' => $childBlueprints,
			'columns' => $columns,
		];
	}

	/**
	 * @param list<Wrapper> $nodes
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

		$children = $this->cms
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

	/**
	 * The flagged sort, or the first sortable column when none is flagged.
	 *
	 * @param array<string, Sort> $sorts
	 */
	private static function defaultSort(array $sorts): string
	{
		$defaults = array_keys(array_filter($sorts, static fn(Sort $sort): bool => $sort->default));

		if (count($defaults) > 1) {
			throw new RuntimeException(
				"Only one collection sort can be the default, found '" . implode("', '", $defaults) . "'",
			);
		}

		$default = $defaults[0] ?? array_key_first($sorts);

		if ($default === null) {
			throw new RuntimeException('Collection listings need at least one sortable column');
		}

		return $default;
	}

	private function resolveOrder(string $sort, string $dir): array
	{
		$sort = trim($sort);
		$sort = $sort === '' ? $this->defaultSort : $sort;
		$definition = $this->sorts[$sort] ?? throw new RuntimeException("Unknown collection sort '{$sort}'");
		$dir = strtolower(trim($dir));
		$dir = $dir === '' ? $definition->direction : $dir;

		return [$sort, $dir, $definition->order($dir)];
	}
}
