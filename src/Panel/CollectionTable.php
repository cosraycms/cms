<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Cosray\CollectionListMeta;
use Cosray\Column;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use Stringable;
use Throwable;

final class CollectionTable
{
	use NormalizesInput;

	/**
	 * @param list<array{label: string, url: ?string, class: string, kind: string}> $headers
	 * @param list<array{
	 *     uid: string,
	 *     depth: int,
	 *     expanded: bool,
	 *     last: bool,
	 *     published: bool,
	 *     hasChildren: bool,
	 *     cells: list<array{label: string, value: string, class: string, editUrl: ?string}>,
	 *     status: list<array{kind: string, label: string}>,
	 *     childrenUrl: ?string,
	 *     focusedChildrenUrl: ?string,
	 *     childCreateLinks: list<array{slug: string, name: string, url: string}>,
	 *     childLinks: list<array{label: string, url: string}>,
	 * }> $rows
	 */
	private function __construct(
		public readonly bool $showChildren,
		public readonly bool $treeMode,
		public readonly array $headers,
		public readonly array $rows,
	) {}

	/**
	 * @param iterable<Column> $columns
	 * @param iterable<mixed> $sortKeys
	 * @param iterable<mixed> $nodes
	 */
	public static function from(
		iterable $columns,
		iterable $sortKeys,
		iterable $nodes,
		CollectionUrls $urls,
		CollectionListMeta $meta,
		string $locale,
		DateTimeZone $timezone,
	): self {
		$nodes = self::items($nodes);
		$headers = self::headers($columns, $sortKeys, $urls);

		return new self(
			showChildren: $meta->showChildren,
			treeMode: $meta->showChildren && $urls->query->view === 'tree',
			headers: $headers,
			rows: self::rows($nodes, $headers, $urls, $meta, $locale, $timezone),
		);
	}

	/**
	 * @param iterable<Column> $columns
	 * @param iterable<mixed> $sortKeys
	 * @return list<array{label: string, url: ?string, class: string, kind: string}>
	 */
	private static function headers(
		iterable $columns,
		iterable $sortKeys,
		CollectionUrls $urls,
	): array {
		$sortKeys = self::sortKeys($sortKeys);
		$headers = [];

		foreach ($columns as $column) {
			$label = $column->title;
			$sort = self::columnSort($column, $sortKeys);
			$isSorted = $sort !== null && $sort === $urls->query->sort;
			$nextDir = $isSorted && $urls->query->dir === 'asc' ? 'desc' : 'asc';
			$class = $sort === null ? '' : 'is-sortable';

			if ($isSorted) {
				$class .= ' is-sorted is-' . $urls->query->dir;
			}

			$headers[] = [
				'label' => $label,
				'url' => $sort === null
					? null
					: $urls->collection(['sort' => $sort, 'dir' => $nextDir, 'offset' => '']),
				'class' => $class,
				'kind' => $column->kind(),
			];
		}

		return $headers;
	}

	/**
	 * @param list<array{label: string, url: ?string, class: string, kind: string}> $headers
	 * @param list<mixed> $nodes
	 * @return list<array{
	 *     uid: string,
	 *     depth: int,
	 *     expanded: bool,
	 *     last: bool,
	 *     published: bool,
	 *     hasChildren: bool,
	 *     cells: list<array{label: string, value: string, class: string, editUrl: ?string}>,
	 *     status: list<array{kind: string, label: string}>,
	 *     childrenUrl: ?string,
	 *     focusedChildrenUrl: ?string,
	 *     childCreateLinks: list<array{slug: string, name: string, url: string}>,
	 *     childLinks: list<array{label: string, url: string}>,
	 * }>
	 */
	private static function rows(
		array $nodes,
		array $headers,
		CollectionUrls $urls,
		CollectionListMeta $meta,
		string $locale,
		DateTimeZone $timezone,
	): array {
		$rows = [];

		foreach ($nodes as $node) {
			$tree = self::treeNode($node);
			$node = $tree['node'];
			$uid = (string) ($node['uid'] ?? '');
			$hasChildren = (bool) ($node['hasChildren'] ?? false);
			$childBlueprints = self::childBlueprints($node);
			$childrenUrl = null;

			if ($meta->showChildren && $hasChildren && $uid !== '') {
				$childrenUrl = $tree['expanded']
					? $urls->collapse($uid, $tree['descendants'])
					: $urls->expand($uid);
			}

			$rows[] = [
				'uid' => $uid,
				'depth' => $tree['depth'],
				'expanded' => $tree['expanded'],
				'last' => $tree['last'],
				'published' => (bool) ($node['published'] ?? false),
				'hasChildren' => $hasChildren,
				'cells' => self::cells($node, $headers, $urls, $locale, $timezone),
				'status' => self::status($node, $meta),
				'childrenUrl' => $childrenUrl,
				'focusedChildrenUrl' =>
					$meta->showChildren && $uid !== '' && ($hasChildren || $childBlueprints !== [])
						? $urls->children($uid)
						: null,
				'childCreateLinks' => $meta->showChildren
					? self::childCreateLinks($childBlueprints, $urls, $uid)
					: [],
				'childLinks' => $meta->showChildren
					? self::childLinks($node, $urls, $childBlueprints)
					: [],
			];
		}

		return $rows;
	}

	/**
	 * @return array{
	 *     node: array<string, mixed>,
	 *     depth: int,
	 *     expanded: bool,
	 *     last: bool,
	 *     descendants: list<string>,
	 * }
	 */
	private static function treeNode(mixed $node): array
	{
		$tree = self::arrayFrom($node);

		if (!array_key_exists('node', $tree)) {
			return [
				'node' => $tree,
				'depth' => 0,
				'expanded' => false,
				'last' => false,
				'descendants' => [],
			];
		}

		return [
			'node' => self::arrayFrom($tree['node']),
			'depth' => max(0, (int) ($tree['depth'] ?? 0)),
			'expanded' => (bool) ($tree['expanded'] ?? false),
			'last' => (bool) ($tree['last'] ?? false),
			'descendants' => self::strings($tree['descendants'] ?? []),
		];
	}

	/**
	 * @param array<string, mixed> $node
	 * @param list<array{label: string, url: ?string, class: string, kind: string}> $headers
	 * @return list<array{label: string, value: string, class: string, editUrl: ?string}>
	 */
	private static function cells(
		array $node,
		array $headers,
		CollectionUrls $urls,
		string $locale,
		DateTimeZone $timezone,
	): array {
		$cells = [];
		$uid = (string) ($node['uid'] ?? '');

		foreach (self::items(self::arrayFrom($node['columns'] ?? [])) as $index => $column) {
			$column = self::arrayFrom($column);
			$label = $headers[$index]['label'] ?? __('collection:column', [
				'number' => (int) $index + 1,
			]);
			$classes = ['collection-cell'];

			if ((bool) ($column['bold'] ?? false)) {
				$classes[] = 'is-bold';
			}

			if ((bool) ($column['italic'] ?? false)) {
				$classes[] = 'is-italic';
			}

			if ((bool) ($column['badge'] ?? false)) {
				$classes[] = 'is-badge';
			}

			$cells[] = [
				'label' => $label,
				'value' => self::displayValue(
					$column['value'] ?? '',
					(bool) ($column['date'] ?? false),
					$locale,
					$timezone,
				),
				'class' => implode(' ', $classes),
				'editUrl' => $index === 0 && $uid !== '' ? $urls->edit($uid) : null,
			];
		}

		return $cells;
	}

	/**
	 * @param list<array{slug: string, name: string}> $blueprints
	 * @return list<array{slug: string, name: string, url: string}>
	 */
	private static function childCreateLinks(
		array $blueprints,
		CollectionUrls $urls,
		string $uid,
	): array {
		$links = [];

		if ($uid === '') {
			return [];
		}

		foreach ($blueprints as $blueprint) {
			$links[] = [
				'slug' => $blueprint['slug'],
				'name' => __($blueprint['name']),
				'url' => $urls->create($blueprint['slug'], $uid),
			];
		}

		return $links;
	}

	/**
	 * @param array<string, mixed> $node
	 * @param list<array{slug: string, name: string}> $blueprints
	 * @return list<array{label: string, url: string}>
	 */
	private static function childLinks(
		array $node,
		CollectionUrls $urls,
		array $blueprints,
	): array {
		$links = [];
		$uid = (string) ($node['uid'] ?? '');

		if ($uid === '') {
			return [];
		}

		if ((bool) ($node['hasChildren'] ?? false)) {
			$links[] = [
				'label' => __('collection:open-children'),
				'url' => $urls->children($uid),
			];
		}

		foreach ($blueprints as $blueprint) {
			$links[] = [
				'label' => __('collection:add', ['name' => __($blueprint['name'])]),
				'url' => $urls->create($blueprint['slug'], $uid),
			];
		}

		return $links;
	}

	/**
	 * @param array<string, mixed> $node
	 * @return list<array{kind: string, label: string}>
	 */
	private static function status(array $node, CollectionListMeta $meta): array
	{
		$badges = [];

		if ($meta->showPublished) {
			$published = (bool) ($node['published'] ?? false);
			$badges[] = [
				'kind' => $published ? 'published' : 'draft',
				'label' => $published ? __('status:published') : __('status:draft'),
			];
		}

		if ($meta->showHidden && (bool) ($node['hidden'] ?? false)) {
			$badges[] = [
				'kind' => 'hidden',
				'label' => __('status:hidden'),
			];
		}

		if ($meta->showLocked && (bool) ($node['locked'] ?? false)) {
			$badges[] = [
				'kind' => 'locked',
				'label' => __('status:locked'),
			];
		}

		return $badges;
	}

	/**
	 * @param array<string, mixed> $node
	 * @return list<array{slug: string, name: string}>
	 */
	private static function childBlueprints(array $node): array
	{
		$blueprints = [];

		foreach (self::items(self::arrayFrom($node['childBlueprints'] ?? [])) as $blueprint) {
			$blueprint = self::arrayFrom($blueprint);
			$slug = trim((string) ($blueprint['slug'] ?? ''));
			$name = trim((string) ($blueprint['name'] ?? ''));

			if ($slug === '' || $name === '') {
				continue;
			}

			$blueprints[] = [
				'slug' => $slug,
				'name' => $name,
			];
		}

		return $blueprints;
	}

	/** @return list<string> */
	private static function sortKeys(iterable $sorts): array
	{
		return array_values(array_filter(
			array_map(static fn(mixed $sort): string => trim((string) $sort), self::items($sorts)),
			static fn(string $sort): bool => $sort !== '',
		));
	}

	/** @param list<string> $sorts */
	private static function columnSort(Column $column, array $sorts): ?string
	{
		$explicit = $column->sortKey();

		if ($explicit !== null) {
			return in_array($explicit, $sorts, true) ? $explicit : null;
		}

		if (!is_string($column->field)) {
			return null;
		}

		$candidates = [$column->field];

		if (str_starts_with($column->field, 'meta.')) {
			$candidates[] = substr($column->field, 5);
		}

		$candidates[] = match ($column->field) {
			'meta.name', 'meta.class', 'meta.classname' => 'type',
			default => '',
		};

		foreach (array_unique($candidates) as $candidate) {
			if ($candidate !== '' && in_array($candidate, $sorts, true)) {
				return $candidate;
			}
		}

		return null;
	}

	private static function displayValue(
		mixed $value,
		bool $date,
		string $locale,
		DateTimeZone $timezone,
	): string {
		if ($date && $value instanceof DateTimeInterface) {
			$formatted = self::formatDate($value, $locale, $timezone);

			if ($formatted !== null) {
				return $formatted;
			}
		}

		if ($date && (is_scalar($value) || $value instanceof Stringable)) {
			$original = $value;
			$value = trim((string) $value);

			if ($value !== '') {
				try {
					$formatted = self::formatDate(
						new DateTimeImmutable($value, $timezone),
						$locale,
						$timezone,
					);

					if ($formatted !== null) {
						return $formatted;
					}
				} catch (Throwable) {
					$value = $original;
				}
			}

			$value = $original;
		}

		if (is_bool($value)) {
			return $value ? __('common:yes') : __('common:no');
		}

		if (is_scalar($value)) {
			return (string) $value;
		}

		if ($value instanceof Stringable) {
			return (string) $value;
		}

		return '';
	}

	private static function formatDate(
		DateTimeInterface $value,
		string $locale,
		DateTimeZone $timezone,
	): ?string {
		$formatter = new IntlDateFormatter(
			$locale,
			IntlDateFormatter::MEDIUM,
			IntlDateFormatter::SHORT,
			$timezone,
		);
		$formatted = $formatter->format($value->getTimestamp());

		return $formatted === false ? null : $formatted;
	}

	/** @return list<string> */
	private static function strings(mixed $items): array
	{
		$strings = [];

		foreach (self::items(self::arrayFrom($items)) as $item) {
			$item = trim((string) $item);

			if ($item !== '' && !in_array($item, $strings, true)) {
				$strings[] = $item;
			}
		}

		return $strings;
	}
}
