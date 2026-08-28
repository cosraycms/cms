<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Cosray\CollectionListMeta;
use Cosray\Column;
use DateTimeZone;
use Traversable;

final class CollectionPage
{
	/**
	 * @param list<array{kind: string, label: string}> $parentStatus
	 * @param list<array{name: string, value: string}> $searchFields
	 * @param list<array{label: string, url: string, active: bool}> $viewLinks
	 * @param list<array{slug: string, name: string, url: string}> $createLinks
	 */
	private function __construct(
		public readonly string $name,
		public readonly string $title,
		public readonly ?string $parentTitle,
		public readonly ?string $parentType,
		public readonly ?string $parentEditUrl,
		public readonly ?string $parentTreeUrl,
		public readonly array $parentStatus,
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
		public readonly array $searchFields,
		public readonly array $viewLinks,
		public readonly array $createLinks,
		public readonly CollectionTable $table,
		public readonly ?string $previousUrl,
		public readonly ?string $nextUrl,
	) {}

	/**
	 * @param iterable<Column> $columns
	 * @param iterable<mixed> $sortKeys
	 * @param iterable<mixed> $blueprints
	 * @param iterable<mixed> $nodes
	 * @param iterable<mixed>|null $createBlueprints
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
		?string $parentTitle = null,
		?string $parentType = null,
		?iterable $parentStatus = null,
		?iterable $createBlueprints = null,
	): self {
		$query = $urls->query;
		$nodes = self::items($nodes);
		$blueprints = self::blueprints($blueprints);
		$createBlueprints = $createBlueprints === null
			? $blueprints
			: self::blueprints($createBlueprints);
		$parentTitle = self::label($parentTitle);
		$parentType = self::label($parentType);
		$parentStatus = self::statusList($parentStatus ?? []);
		$pageCount = $query->limit > 0 ? max(1, (int) ceil($total / $query->limit)) : 1;
		$currentPage = $query->limit > 0
			? min($pageCount, (int) floor($query->offset / $query->limit) + 1)
			: 1;
		$rowCount = count($nodes);
		$rangeStart = $total === 0 ? 0 : min($query->offset + 1, $total);
		$rangeEnd = min($query->offset + $rowCount, $total);

		return new self(
			name: $name,
			title: $parentTitle ?? $name,
			parentTitle: $parentTitle,
			parentType: $parentType,
			parentEditUrl: $query->parent === null ? null : $urls->edit($query->parent),
			parentTreeUrl: $query->parent === null ? null : $urls->showInTree($query->parent),
			parentStatus: $parentStatus,
			urls: $urls,
			query: $query,
			path: $urls->path(),
			clearSearchUrl: $query->q === ''
				? null
				: $urls->collection(['q' => '', 'offset' => '']),
			rootUrl: $query->parent === null
				? null
				: $urls->collection([
					'parent' => '',
					'offset' => '',
					'view' => '',
					'open' => '',
				]),
			total: $total,
			pageCount: $pageCount,
			currentPage: $currentPage,
			rangeStart: $rangeStart,
			rangeEnd: $rangeEnd,
			searchFields: self::searchFields($query),
			viewLinks: self::viewLinks($meta, $query, $urls),
			createLinks: self::createLinks($createBlueprints, $urls),
			table: CollectionTable::from(
				columns: $columns,
				sortKeys: $sortKeys,
				nodes: $nodes,
				urls: $urls,
				meta: $meta,
				locale: $locale,
				timezone: $timezone,
			),
			previousUrl: $query->offset > 0
				? $urls->collection(['offset' => max(0, $query->offset - $query->limit)])
				: null,
			nextUrl: ($query->offset + $query->limit) < $total
				? $urls->collection(['offset' => $query->offset + $query->limit])
				: null,
		);
	}

	private static function label(?string $label): ?string
	{
		$label = trim((string) $label);

		return $label === '' ? null : $label;
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

		if ($query->view !== $query->defaultView) {
			$fields[] = ['name' => 'view', 'value' => $query->view];
		}

		if ($query->open !== []) {
			$fields[] = ['name' => 'open', 'value' => implode(',', $query->open)];
		}

		return $fields;
	}

	/** @return list<array{label: string, url: string, active: bool}> */
	private static function viewLinks(
		CollectionListMeta $meta,
		CollectionQuery $query,
		CollectionUrls $urls,
	): array {
		if (!$meta->showChildren || $query->parent !== null) {
			return [];
		}

		return [
			[
				'label' => __('collection:tree'),
				'url' => $urls->collection([
					'view' => 'tree',
					'open' => '',
					'offset' => '',
				]),
				'active' => $query->view === 'tree',
			],
			[
				'label' => __('collection:list'),
				'url' => $urls->collection([
					'view' => 'list',
					'open' => '',
					'offset' => '',
				]),
				'active' => $query->view === 'list',
			],
		];
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
				'name' => __($blueprint['name']),
				'url' => $urls->create($blueprint['slug']),
			];
		}

		return $links;
	}

	/** @return list<array{kind: string, label: string}> */
	private static function statusList(iterable $status): array
	{
		$badges = [];

		foreach ($status as $badge) {
			$badge = self::arrayFrom($badge);
			$kind = trim((string) ($badge['kind'] ?? ''));
			$label = trim((string) ($badge['label'] ?? ''));

			if ($kind === '' || $label === '') {
				continue;
			}

			$badges[] = [
				'kind' => $kind,
				'label' => $label,
			];
		}

		return $badges;
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
