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
	 * @param array{publishUrl: string, deleteUrl: string, duplicateUrl: string, showPublished: bool} $bulk
	 * @param list<array{key: string, label: string, count: int, url: string, active: bool}> $stages
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
		public readonly array $stages = [],
	) {}

	/**
	 * @param iterable<Column> $columns
	 * @param iterable<mixed> $blueprints
	 * @param iterable<mixed> $nodes
	 * @param iterable<mixed>|null $createBlueprints
	 * @param iterable<mixed>|null $parentStatus
	 * @param ?array{stages: list<array<string, string>>, sentence: string} $parentDots
	 * @param array<string, int> $stages how many entries each status stage holds
	 */
	public static function from(
		string $name,
		CollectionUrls $urls,
		iterable $columns,
		iterable $blueprints,
		iterable $nodes,
		int $total,
		CollectionListMeta $meta,
		string $locale,
		DateTimeZone $timezone,
		?string $parentTitle = null,
		?string $parentType = null,
		?iterable $parentStatus = null,
		?array $parentDots = null,
		?iterable $createBlueprints = null,
		bool $arranged = false,
		string $moved = '',
		array $stages = [],
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
			dots: $parentDots,
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
				nodes: $nodes,
				urls: $urls,
				meta: $meta,
				locale: $locale,
				timezone: $timezone,
				arranged: $arranged,
				moved: $moved,
			),
			pager: CollectionPager::from($total, count($nodes), $urls),
			bulk: [
				'publishUrl' => $urls->bulk('publish'),
				'deleteUrl' => $urls->bulk('delete'),
				'duplicateUrl' => $urls->bulk('duplicate'),
				'showPublished' => $meta->showPublished,
			],
			stages: self::stages($stages, $query, $urls),
		);
	}

	/**
	 * The status filters above the list, each with its count.
	 *
	 * @param array<string, int> $counts
	 * @return list<array{key: string, label: string, count: int, url: string, active: bool}>
	 */
	private static function stages(array $counts, CollectionQuery $query, CollectionUrls $urls): array
	{
		// Literal ids so the i18n scanner sees every key.
		$labels = [
			'all' => __('collection:stage-all'),
			'draft' => __('collection:stage-draft'),
			'mine' => __('collection:stage-mine'),
			'offline' => __('collection:stage-offline'),
			'unreleased' => __('collection:stage-unreleased'),
		];
		$active = $query->stage === '' ? 'all' : $query->stage;
		$chips = [];

		foreach ($counts as $key => $count) {
			$chips[] = [
				'key' => $key,
				'label' => $labels[$key] ?? $key,
				'count' => $count,
				'url' => $urls->collection(['stage' => $key === 'all' ? '' : $key, 'offset' => '']),
				'active' => $key === $active,
			];
		}

		return $chips;
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

	/**
	 * @param iterable<mixed> $blueprints
	 * @return list<array{slug: string, name: string}>
	 */
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
