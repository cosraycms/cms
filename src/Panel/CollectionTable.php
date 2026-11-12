<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Cosray\CollectionListMeta;
use Cosray\Column;
use Cosray\Stage;
use Cosray\StatusColumn;
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
	 * @param list<array{label: string, name: string, url: ?string, class: string, kind: string, ariaSort: ?string, lead: bool, stages: list<string>, look: string, present: bool, badges: bool}> $headers
	 * @param list<array{
	 *     uid: string,
	 *     depth: int,
	 *     expanded: bool,
	 *     last: bool,
	 *     published: bool,
	 *     hasChildren: bool,
	 *     cells: list<array{label: string, value: string, class: string, editUrl: ?string, lead: bool, status: ?array{stages: list<array{key: string, state: string, name: string, text: string, help: string, badge: string}>, sentence: string, look: string, badges: list<array{kind: string, label: string}>}}>,
	 *     childrenUrl: ?string,
	 *     focusedChildrenUrl: ?string,
	 *     childCreateLinks: list<array{slug: string, name: string, url: string}>,
	 *     group: ?string,
	 *     moveUp: bool,
	 *     moveDown: bool,
	 *     moved: bool,
	 * }> $rows
	 */
	private function __construct(
		public readonly bool $showChildren,
		public readonly bool $treeMode,
		public readonly array $headers,
		public readonly array $rows,
		public readonly string $positionUrl,
	) {}

	/**
	 * @param iterable<Column|StatusColumn> $columns
	 * @param iterable<mixed> $nodes
	 */
	public static function from(
		iterable $columns,
		iterable $nodes,
		CollectionUrls $urls,
		CollectionListMeta $meta,
		string $locale,
		DateTimeZone $timezone,
		bool $arranged = false,
		string $moved = '',
	): self {
		$nodes = self::items($nodes);
		$headers = self::headers($columns, $urls, $arranged);

		return new self(
			showChildren: $meta->showChildren,
			treeMode: $meta->showChildren && $urls->query->view === 'tree',
			headers: $headers,
			rows: self::rows($nodes, $headers, $urls, $meta, $locale, $timezone, $moved),
			positionUrl: $urls->position(),
		);
	}

	/**
	 * The column heads. The first field column leads: it links the entry
	 * and carries the tree. Hidden and locked badges join the stages of
	 * the main version, or else the first stages shown.
	 *
	 * @param iterable<Column|StatusColumn> $columns
	 * @return list<array{label: string, name: string, url: ?string, class: string, kind: string, ariaSort: ?string, lead: bool, stages: list<string>, look: string, present: bool, badges: bool}>
	 */
	private static function headers(
		iterable $columns,
		CollectionUrls $urls,
		bool $arranged,
	): array {
		$headers = [];
		$lead = false;

		foreach ($columns as $column) {
			if ($column instanceof StatusColumn) {
				$label = $column->label();
				$headers[] = [
					'label' => $label,
					'name' => $label !== '' ? $label : __('collection:status'),
					'url' => null,
					'class' => 'col-status',
					'kind' => 'status',
					'ariaSort' => null,
					'lead' => false,
					'stages' => array_map(static fn(Stage $stage): string => $stage->value, $column->stages),
					'look' => $column->look,
					'present' => $column->present,
					'badges' => false,
				];

				continue;
			}

			$label = $column->title;
			// A manually ordered top level has no column order to offer.
			$sort = $arranged ? null : $column->sort;
			$isSorted = $sort !== null && $sort->key === $urls->query->sort;
			$nextDir = $sort?->direction;
			$ariaSort = null;
			$class = $sort === null ? '' : 'is-sortable';

			if ($isSorted) {
				$ascending = $urls->query->dir === 'asc';
				$nextDir = $ascending ? 'desc' : 'asc';
				$ariaSort = $ascending ? 'ascending' : 'descending';
				$class .= ' is-sorted is-' . $urls->query->dir;
			}

			$headers[] = [
				'label' => $label,
				'name' => $label,
				'url' => $sort === null
					? null
					: $urls->collection(['sort' => $sort->key, 'dir' => $nextDir, 'offset' => '']),
				'class' => $class,
				'kind' => $column->kind(),
				'ariaSort' => $ariaSort,
				'lead' => !$lead,
				'stages' => [],
				'look' => '',
				'present' => false,
				'badges' => false,
			];
			$lead = true;
		}

		$status = array_keys(array_filter($headers, static fn(array $header): bool => $header['kind'] === 'status'));
		$main = array_values(array_filter(
			$status,
			static fn(int $index): bool => in_array(Stage::Main->value, $headers[$index]['stages'], true),
		));
		$badges = $main[0] ?? $status[0] ?? null;

		return array_map(
			static fn(int $index, array $header): array => ['badges' => $index === $badges] + $header,
			array_keys($headers),
			$headers,
		);
	}

	/**
	 * @param list<array{label: string, name: string, url: ?string, class: string, kind: string, ariaSort: ?string, lead: bool, stages: list<string>, look: string, present: bool, badges: bool}> $headers
	 * @param list<mixed> $nodes
	 * @return list<array{
	 *     uid: string,
	 *     depth: int,
	 *     expanded: bool,
	 *     last: bool,
	 *     published: bool,
	 *     hasChildren: bool,
	 *     cells: list<array{label: string, value: string, class: string, editUrl: ?string, lead: bool, status: ?array{stages: list<array{key: string, state: string, name: string, text: string, help: string, badge: string}>, sentence: string, look: string, badges: list<array{kind: string, label: string}>}}>,
	 *     childrenUrl: ?string,
	 *     focusedChildrenUrl: ?string,
	 *     childCreateLinks: list<array{slug: string, name: string, url: string}>,
	 *     group: ?string,
	 *     moveUp: bool,
	 *     moveDown: bool,
	 *     moved: bool,
	 * }>
	 */
	private static function rows(
		array $nodes,
		array $headers,
		CollectionUrls $urls,
		CollectionListMeta $meta,
		string $locale,
		DateTimeZone $timezone,
		string $moved,
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
				'guides' => $tree['guides'],
				'published' => (bool) ($node['published'] ?? false),
				'hasChildren' => $hasChildren,
				'cells' => self::cells($node, $headers, $urls, $meta, $locale, $timezone),
				'childrenUrl' => $childrenUrl,
				'focusedChildrenUrl' =>
					$meta->showChildren && $uid !== '' && ($hasChildren || $childBlueprints !== [])
						? $urls->children($uid)
						: null,
				'childCreateLinks' => $meta->showChildren
					? self::childCreateLinks($childBlueprints, $urls, $uid)
					: [],
				'group' => is_string($node['group'] ?? null) ? $node['group'] : null,
				'moveUp' => (bool) ($node['moveUp'] ?? false),
				'moveDown' => (bool) ($node['moveDown'] ?? false),
				'moved' => $moved !== '' && $uid === $moved,
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
	 *     guides: list<int>,
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
				'guides' => [],
				'descendants' => [],
			];
		}

		return [
			'node' => self::arrayFrom($tree['node']),
			'depth' => max(0, (int) ($tree['depth'] ?? 0)),
			'expanded' => (bool) ($tree['expanded'] ?? false),
			'last' => (bool) ($tree['last'] ?? false),
			'guides' => array_values(array_map(intval(...), (array) ($tree['guides'] ?? []))),
			'descendants' => self::strings($tree['descendants'] ?? []),
		];
	}

	/**
	 * @param array<string, mixed> $node
	 * @param list<array{label: string, name: string, url: ?string, class: string, kind: string, ariaSort: ?string, lead: bool, stages: list<string>, look: string, present: bool, badges: bool}> $headers
	 * @return list<array{label: string, value: string, class: string, editUrl: ?string, lead: bool, status: ?array{stages: list<array{key: string, state: string, name: string, text: string, help: string, badge: string}>, sentence: string, look: string, badges: list<array{kind: string, label: string}>}}>
	 */
	private static function cells(
		array $node,
		array $headers,
		CollectionUrls $urls,
		CollectionListMeta $meta,
		string $locale,
		DateTimeZone $timezone,
	): array {
		$cells = [];
		$uid = (string) ($node['uid'] ?? '');
		$stages = null;

		foreach (self::items(self::arrayFrom($node['columns'] ?? [])) as $index => $column) {
			$column = self::arrayFrom($column);
			$header = $headers[$index] ?? null;
			$label = $header['name'] ?? __('collection:column', [
				'number' => (int) $index + 1,
			]);

			if ($header !== null && $header['kind'] === 'status') {
				$stages ??= self::dots($node, $locale, $timezone)['stages'] ?? [];
				$cells[] = [
					'label' => $label,
					'value' => '',
					'class' => 'col-status',
					'editUrl' => null,
					'lead' => false,
					'status' => self::stagesOf($header, $stages, $header['badges'] ? self::status($node, $meta) : []),
				];

				continue;
			}

			$lead = $header === null ? $index === 0 : $header['lead'];
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
				'editUrl' => $lead && $uid !== '' ? $urls->edit($uid) : null,
				'lead' => $lead,
				'status' => null,
			];
		}

		return $cells;
	}

	/**
	 * A status column's share of a row's stages. A record's saved state
	 * counts as its main version. Compact names every stage it stands for,
	 * the others only those they show.
	 *
	 * @param array{label: string, name: string, url: ?string, class: string, kind: string, ariaSort: ?string, lead: bool, stages: list<string>, look: string, present: bool, badges: bool} $header
	 * @param list<array{key: string, state: string, name: string, text: string, help: string, badge: string}> $stages
	 * @param list<array{kind: string, label: string}> $badges
	 * @return array{stages: list<array{key: string, state: string, name: string, text: string, help: string, badge: string}>, sentence: string, look: string, badges: list<array{kind: string, label: string}>}
	 */
	private static function stagesOf(array $header, array $stages, array $badges): array
	{
		$chosen = array_values(array_filter(
			$stages,
			static fn(array $stage): bool => in_array(
				$stage['key'] === 'saved' ? Stage::Main->value : $stage['key'],
				$header['stages'],
				true,
			),
		));
		$shown = $header['present']
			? array_values(array_filter($chosen, static fn(array $stage): bool => $stage['state'] !== 'none'))
			: $chosen;
		return [
			'stages' => $shown,
			'sentence' => NodeStatus::sentence($header['look'] === 'compact' ? $chosen : $shown),
			'look' => $header['look'],
			'badges' => $badges,
		];
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
	 * The row's stages as Panel\NodeStatus derives them, for its dots.
	 *
	 * @param array<string, mixed> $node
	 * @return ?array{stages: list<array{key: string, state: string, name: string, text: string, help: string, badge: string}>, sentence: string}
	 */
	public static function dots(array $node, string $locale, DateTimeZone $timezone): ?array
	{
		$facts = $node['stages'] ?? null;

		if (!is_array($facts)) {
			return null;
		}

		$draft = is_array($facts['draft'] ?? null) ? $facts['draft'] : null;
		$mine = is_array($facts['mine'] ?? null) ? $facts['mine'] : null;
		$stages = NodeStatus::of(
			record: (bool) ($facts['record'] ?? false),
			published: (bool) ($node['published'] ?? false),
			edited: (string) ($facts['edited'] ?? ''),
			editor: is_string($facts['editor'] ?? null) ? $facts['editor'] : null,
			draft: $draft === null
				? null
				: [
					'changed' => (string) ($draft['changed'] ?? ''),
					'editorName' => is_string($draft['editorName'] ?? null) ? $draft['editorName'] : null,
				],
			autosave: $mine === null
				? null
				: ['changed' => (string) ($mine['changed'] ?? ''), 'base' => (string) ($mine['base'] ?? '')],
		)->stages(NodeStatus::dates($locale, $timezone));

		return ['stages' => $stages, 'sentence' => NodeStatus::sentence($stages)];
	}

	/**
	 * Badges for states besides the stages: hidden and locked.
	 *
	 * @param array<string, mixed> $node
	 * @return list<array{kind: string, label: string}>
	 */
	private static function status(array $node, CollectionListMeta $meta): array
	{
		$badges = [];

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
