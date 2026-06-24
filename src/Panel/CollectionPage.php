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
use Traversable;

final class CollectionPage
{
	/**
	 * @param list<array{name: string, value: string}> $searchFields
	 * @param list<array{slug: string, name: string, url: string}> $createLinks
	 * @param list<array{label: string, url: ?string, class: string}> $headers
	 * @param list<array{
	 *     uid: string,
	 *     cells: list<array{label: string, value: string, class: string, editUrl: ?string}>,
	 *     status: list<array{kind: string, label: string}>,
	 *     childLinks: list<array{label: string, url: string}>,
	 * }> $rows
	 */
	private function __construct(
		public readonly string $name,
		public readonly CollectionUrls $urls,
		public readonly CollectionQuery $query,
		public readonly string $path,
		public readonly ?string $clearSearchUrl,
		public readonly ?string $rootUrl,
		public readonly int $total,
		public readonly int $pageCount,
		public readonly int $currentPage,
		public readonly int $rangeStart,
		public readonly int $rangeEnd,
		public readonly bool $showChildren,
		public readonly array $searchFields,
		public readonly array $createLinks,
		public readonly array $headers,
		public readonly array $rows,
		public readonly ?string $previousUrl,
		public readonly ?string $nextUrl,
	) {}

	/**
	 * @param iterable<Column> $columns
	 * @param iterable<mixed> $sortKeys
	 * @param iterable<mixed> $blueprints
	 * @param iterable<mixed> $nodes
	 */
	public static function from(
		string $name,
		CollectionUrls $urls,
		iterable $columns,
		iterable $sortKeys,
		iterable $blueprints,
		iterable $nodes,
		int $total,
		CollectionListMeta $meta,
		string $locale,
		DateTimeZone $timezone,
	): self {
		$query = $urls->query;
		$nodes = self::items($nodes);
		$blueprints = self::blueprints($blueprints);
		$headers = self::headers($columns, $sortKeys, $urls);
		$pageCount = $query->limit > 0 ? max(1, (int) ceil($total / $query->limit)) : 1;
		$currentPage = $query->limit > 0
			? min($pageCount, (int) floor($query->offset / $query->limit) + 1)
			: 1;
		$rowCount = count($nodes);
		$rangeStart = $total === 0 ? 0 : min($query->offset + 1, $total);
		$rangeEnd = min($query->offset + $rowCount, $total);

		return new self(
			name: $name,
			urls: $urls,
			query: $query,
			path: $urls->path(),
			clearSearchUrl: $query->q === ''
				? null
				: $urls->collection(['q' => '', 'offset' => '']),
			rootUrl: $query->parent === null
				? null
				: $urls->collection(['parent' => '', 'offset' => '']),
			total: $total,
			pageCount: $pageCount,
			currentPage: $currentPage,
			rangeStart: $rangeStart,
			rangeEnd: $rangeEnd,
			showChildren: $meta->showChildren,
			searchFields: self::searchFields($query),
			createLinks: self::createLinks($blueprints, $urls),
			headers: $headers,
			rows: self::rows($nodes, $headers, $blueprints, $urls, $meta, $locale, $timezone),
			previousUrl: $query->offset > 0
				? $urls->collection(['offset' => max(0, $query->offset - $query->limit)])
				: null,
			nextUrl: ($query->offset + $query->limit) < $total
				? $urls->collection(['offset' => $query->offset + $query->limit])
				: null,
		);
	}

	/** @return list<array{name: string, value: string}> */
	private static function searchFields(CollectionQuery $query): array
	{
		$fields = [];

		if ($query->sort !== '') {
			$fields[] = ['name' => 'sort', 'value' => $query->sort];
		}

		if ($query->dir !== '') {
			$fields[] = ['name' => 'dir', 'value' => $query->dir];
		}

		if ($query->limit !== 50) {
			$fields[] = ['name' => 'limit', 'value' => (string) $query->limit];
		}

		if ($query->parent !== null) {
			$fields[] = ['name' => 'parent', 'value' => $query->parent];
		}

		return $fields;
	}

	/**
	 * @param iterable<Column> $columns
	 * @param iterable<mixed> $sortKeys
	 * @return list<array{label: string, url: ?string, class: string}>
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
			];
		}

		return $headers;
	}

	/**
	 * @param list<array{label: string, url: ?string, class: string}> $headers
	 * @param list<array{slug: string, name: string}> $blueprints
	 * @param list<mixed> $nodes
	 * @return list<array{
	 *     uid: string,
	 *     cells: list<array{label: string, value: string, class: string, editUrl: ?string}>,
	 *     status: list<array{kind: string, label: string}>,
	 *     childLinks: list<array{label: string, url: string}>,
	 * }>
	 */
	private static function rows(
		array $nodes,
		array $headers,
		array $blueprints,
		CollectionUrls $urls,
		CollectionListMeta $meta,
		string $locale,
		DateTimeZone $timezone,
	): array {
		$rows = [];
		$blueprintSlugs = array_column($blueprints, 'slug');

		foreach ($nodes as $node) {
			$node = self::arrayFrom($node);
			$uid = (string) ($node['uid'] ?? '');
			$rows[] = [
				'uid' => $uid,
				'cells' => self::cells($node, $headers, $urls, $locale, $timezone),
				'status' => self::status($node, $meta),
				'childLinks' => $meta->showChildren
					? self::childLinks($node, $blueprintSlugs, $urls)
					: [],
			];
		}

		return $rows;
	}

	/**
	 * @param array<string, mixed> $node
	 * @param list<array{label: string, url: ?string, class: string}> $headers
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
			$label = $headers[$index]['label'] ?? 'Column ' . ((int) $index + 1);
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
	private static function createLinks(array $blueprints, CollectionUrls $urls): array
	{
		$links = [];

		foreach ($blueprints as $blueprint) {
			$links[] = [
				'slug' => $blueprint['slug'],
				'name' => $blueprint['name'],
				'url' => $urls->create($blueprint['slug']),
			];
		}

		return $links;
	}

	/**
	 * @param array<string, mixed> $node
	 * @param list<string> $blueprintSlugs
	 * @return list<array{label: string, url: string}>
	 */
	private static function childLinks(
		array $node,
		array $blueprintSlugs,
		CollectionUrls $urls,
	): array {
		$links = [];
		$uid = (string) ($node['uid'] ?? '');

		if ($uid === '') {
			return [];
		}

		if ((bool) ($node['hasChildren'] ?? false)) {
			$links[] = [
				'label' => 'Open children',
				'url' => $urls->collection(['parent' => $uid, 'offset' => '']),
			];
		}

		foreach (self::childBlueprints($node, $blueprintSlugs) as $blueprint) {
			$links[] = [
				'label' => 'Add ' . $blueprint['name'],
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
				'label' => $published ? 'Published' : 'Draft',
			];
		}

		if ($meta->showHidden && (bool) ($node['hidden'] ?? false)) {
			$badges[] = [
				'kind' => 'hidden',
				'label' => 'Hidden',
			];
		}

		if ($meta->showLocked && (bool) ($node['locked'] ?? false)) {
			$badges[] = [
				'kind' => 'locked',
				'label' => 'Locked',
			];
		}

		return $badges;
	}

	/**
	 * @param array<string, mixed> $node
	 * @param list<string> $allowedSlugs
	 * @return list<array{slug: string, name: string}>
	 */
	private static function childBlueprints(array $node, array $allowedSlugs): array
	{
		$blueprints = [];

		foreach (self::items(self::arrayFrom($node['childBlueprints'] ?? [])) as $blueprint) {
			$blueprint = self::arrayFrom($blueprint);
			$slug = trim((string) ($blueprint['slug'] ?? ''));
			$name = trim((string) ($blueprint['name'] ?? ''));

			if ($slug === '' || $name === '' || !in_array($slug, $allowedSlugs, true)) {
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

	/** @return list<array{slug: string, name: string}> */
	private static function blueprints(iterable $blueprints): array
	{
		$result = [];

		foreach ($blueprints as $blueprint) {
			$blueprint = self::arrayFrom($blueprint);
			$slug = trim((string) ($blueprint['slug'] ?? ''));
			$name = trim((string) ($blueprint['name'] ?? ''));

			if ($slug === '' || $name === '') {
				continue;
			}

			$result[] = [
				'slug' => $slug,
				'name' => $name,
			];
		}

		return $result;
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
			return $value ? 'Yes' : 'No';
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

	/** @return list<mixed> */
	private static function items(iterable $items): array
	{
		if ($items instanceof Traversable) {
			return array_values(iterator_to_array($items, false));
		}

		return array_values($items);
	}

	/** @return array<array-key, mixed> */
	private static function arrayFrom(mixed $value): array
	{
		if ($value instanceof Traversable) {
			return iterator_to_array($value);
		}

		return is_array($value) ? $value : [];
	}
}
