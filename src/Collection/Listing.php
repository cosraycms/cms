<?php

declare(strict_types=1);

namespace Cosray\Collection;

use Cosray\Cms;
use Cosray\CollectionListMeta;
use Cosray\Column;
use Cosray\Contract\Columns;
use Cosray\Contract\Entries;
use Cosray\Exception\RuntimeException;
use Cosray\Finder\Nodes;
use Cosray\Finder\Order;
use Cosray\Finder\SortField;
use Cosray\Node\Positions;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;

/**
 * A registered collection as the panel lists it: its entries, columns,
 * sorts, list options and blueprints, plus the row and hierarchy logic
 * of collection pages.
 *
 * Collections are plain classes configured through schema attributes.
 * The instance is only needed when it implements Entries or Columns.
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
		private readonly Schema $schema,
		private readonly Cms $cms,
		private readonly Types $types,
		private readonly ?object $collection = null,
	) {
		$this->meta = $schema->listing;
		$this->blueprints = $schema->blueprints;
		$this->columns = array_values($collection instanceof Columns ? $collection->columns() : Column::defaults());
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
		$nodes = $this->cms->nodes()->published(null)->hidden(null);

		if ($this->schema->types !== []) {
			$nodes->types(...$this->schema->types);
		}

		return $this->collection instanceof Entries ? $this->collection->entries($nodes) : $nodes;
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
		$parent = $this->meta->showChildren ? trim((string) $parent) : '';

		if ($this->meta->showChildren) {
			if ($parent === '') {
				$nodes->roots();
			} else {
				$nodes->childrenOf($parent);
			}
		}

		// A manually ordered level ignores the column sort: the tree shares
		// one header row between several levels, and a column click must
		// not re-sort some of them.
		$scope = $this->scope($parent);

		if ($scope !== null) {
			$order = [$this->positionOrder($scope)];
		}

		$q = trim($q);

		if ($q !== '') {
			$nodes->search($q, $this->schema->search);
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
			'arranged' => $scope !== null,
			// A search shows a subset, where moving next to a hit would
			// silently jump the rows in between.
			'nodes' => $this->rows($pageNodes, $q === '' ? $scope : null, $offset, $total),
		];
	}

	/**
	 * Moves an entry directly before or after a neighbour in the manual
	 * order it belongs to: its parent's children in a hierarchy listing,
	 * otherwise the collection's own top level. The whole group is
	 * renumbered, members this listing doesn't show included.
	 */
	public function place(Positions $positions, string $uid, string $neighbour, bool $after): void
	{
		$scope = $this->scopeOf($uid);
		$order = Positions::move($this->uids($this->group($scope)), $uid, $neighbour, $after);

		if ($scope === '') {
			$positions->orderEntries($this->handle(), $order);

			return;
		}

		$positions->orderChildren($scope, $order);
	}

	/**
	 * The row above or below an entry as this listing shows its group, so
	 * a move always changes what the editor sees, even with sibling types
	 * the collection doesn't list in between. Null at either end.
	 */
	public function neighbour(string $uid, bool $below): ?string
	{
		$scope = $this->scopeOf($uid);
		$group = $scope === ''
			? $this->group($scope)
			: $this->entries()->childrenOf($scope)->order($this->positionOrder($scope));
		$uids = $this->uids($group);
		$index = array_search($uid, $uids, true);

		if ($index === false) {
			return null;
		}

		return $uids[$below ? $index + 1 : $index - 1] ?? null;
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
	 * @param ?string $group the manual order the rows can be moved in, null when they can't
	 */
	private function rows(array $nodes, ?string $group, int $offset, int $total): array
	{
		$result = [];
		$hasChildren = $this->meta->showChildren
			? $this->hasChildrenMap($nodes)
			: [];

		foreach (array_values($nodes) as $index => $node) {
			$position = $offset + $index;
			$result[] = [
				...$this->row($node, $hasChildren[$node->meta->uid] ?? false),
				'group' => $group,
				'moveUp' => $group !== null && $position > 0,
				'moveDown' => $group !== null && $position < ($total - 1),
			];
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
	 * The manual order a level follows: the parent's uid when its type
	 * declares sortable children, an empty string for the top level of a
	 * sortable listing, null when the columns decide.
	 */
	private function scope(string $parent): ?string
	{
		if ($parent === '') {
			return $this->meta->sortable ? '' : null;
		}

		$node = $this->parent($parent);

		return $node?->meta->type->get('sortableChildren', false) === true ? $parent : null;
	}

	/** The scope an entry of this listing is moved in. */
	private function scopeOf(string $uid): string
	{
		$node = iterator_to_array($this->entries()->only($uid))[0] ?? null;

		if (!$node instanceof Wrapper) {
			throw new RuntimeException("'{$uid}' is not an entry of this collection");
		}

		$parent = $node->meta->get('parent');
		$level = $this->meta->showChildren && is_string($parent) ? $parent : '';

		return $this->scope($level) ?? throw new RuntimeException("'{$uid}' is not in a manual order");
	}

	/**
	 * Every member of a manual order in its current order: all children of
	 * the parent, whatever this collection lists, because templates read
	 * the whole group through children(); or the collection's top level.
	 */
	private function group(string $scope): Nodes
	{
		if ($scope !== '') {
			return $this->cms
				->nodes()
				->published(null)
				->hidden(null)
				->childrenOf($scope)
				->order($this->positionOrder($scope));
		}

		$nodes = $this->entries();

		if ($this->meta->showChildren) {
			$nodes->roots();
		}

		return $nodes->order($this->positionOrder($scope));
	}

	private function positionOrder(string $scope): Order
	{
		return new Order($scope === '' ? SortField::position($this->handle()) : SortField::position());
	}

	private function handle(): string
	{
		return (string) $this->schema->handle;
	}

	/** @return list<string> */
	private function uids(Nodes $nodes): array
	{
		$uids = [];

		foreach ($nodes as $node) {
			$uids[] = $node->meta->uid;
		}

		return $uids;
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
