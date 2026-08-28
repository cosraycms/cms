<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Cosray\CollectionListMeta;
use Cosray\Column;
use DateTimeZone;

final class CollectionPage
{
	use NormalizesInput;

	/**
	 * @param list<array{label: string, url: string, active: bool}> $viewLinks
	 * @param list<array{slug: string, name: string, url: string}> $createLinks
	 * @param array{publishUrl: string, deleteUrl: string, showPublished: bool} $bulk
	 */
	private function __construct(
		public readonly string $name,
		public readonly string $title,
		public readonly ?CollectionParent $parent,
		public readonly CollectionSearch $search,
		public readonly array $viewLinks,
		public readonly array $createLinks,
		public readonly CollectionTable $table,
		public readonly CollectionPager $pager,
		public readonly array $bulk,
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
		$parent = CollectionParent::from(
			urls: $urls,
			title: $parentTitle,
			type: $parentType,
			status: $parentStatus ?? [],
		);

		return new self(
			name: $name,
			title: self::label($parentTitle) ?? $name,
			parent: $parent,
			search: CollectionSearch::from($urls),
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
			pager: CollectionPager::from($total, count($nodes), $urls),
			bulk: [
				'publishUrl' => $urls->bulk('publish'),
				'deleteUrl' => $urls->bulk('delete'),
				'showPublished' => $meta->showPublished,
			],
		);
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
}
