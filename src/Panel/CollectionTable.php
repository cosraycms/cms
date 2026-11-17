<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Cosray\CollectionListMeta;
use Cosray\Column;
use Cosray\Fulltext\Marker;
use Cosray\Fulltext\Query;
use Cosray\Stage;
use Cosray\StatusColumn;
use Cosray\User\Avatar;
use Cosray\UserColumn;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use NumberFormatter;
use Stringable;
use Throwable;

final class CollectionTable
{
	use NormalizesInput;

	/**
	 * @param list<array{label: string, name: string, url: ?string, class: string, kind: string, ariaSort: ?string, lead: bool, stages: list<string>, look: string, fixed: bool, badges: bool, searched: bool}> $headers
	 * @param list<array{
	 *     uid: string,
	 *     depth: int,
	 *     expanded: bool,
	 *     last: bool,
	 *     published: bool,
	 *     hasChildren: bool,
	 *     path: list<string>,
	 *     snippet: list<array{text: string, match: bool}>,
	 *     cells: list<array{label: string, value: string, class: string, editUrl: ?string, lead: bool, avatar: ?array{label: string, initials: string, hue: int}, avatarOnly: bool, marks: ?list<array{text: string, match: bool}>, status: ?array{stages: list<array{key: string, state: string, name: string, text: string, help: string, badge: string}>, sentence: string, look: string, fixed: bool, badges: list<array{kind: string, label: string}>}}>,
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
	 * @param iterable<Column> $columns
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
		bool $drafts = true,
	): self {
		$nodes = self::items($nodes);
		$headers = self::headers($columns, $urls, $arranged, $drafts);

		return new self(
			showChildren: $meta->showChildren,
			// Filtered results come from every level, so they list flat.
			treeMode: $meta->showChildren
				&& $urls->query->view === 'tree'
				&& $urls->query->q === ''
				&& $urls->query->stage === ''
				&& $urls->query->type === '',
			headers: $headers,
			rows: self::rows(
				$nodes,
				$headers,
				$urls,
				$meta,
				$locale,
				$timezone,
				$moved,
				$urls->query->q === '' ? null : new Marker(Query::parse($urls->query->q)),
			),
			positionUrl: $urls->position(),
		);
	}

	/**
	 * The column heads. The first field column leads: it links the entry
	 * and carries the tree. Hidden and locked badges join the stages of
	 * the main version, or else the first stages shown. A status column
	 * has a slot for each of its stages, but none for drafts in a
	 * collection of types without them.
	 *
	 * @param iterable<Column> $columns
	 * @return list<array{label: string, name: string, url: ?string, class: string, kind: string, ariaSort: ?string, lead: bool, stages: list<string>, look: string, fixed: bool, badges: bool, searched: bool}>
	 */
	private static function headers(
		iterable $columns,
		CollectionUrls $urls,
		bool $arranged,
		bool $drafts,
	): array {
		$headers = [];
		$lead = false;

		foreach ($columns as $column) {
			if ($column instanceof StatusColumn) {
				$label = $column->title;
				$headers[] = [
					'label' => $label,
					'name' => $label !== '' ? $label : __('collection:status'),
					'url' => null,
					'class' => 'cms-list-col-status',
					'kind' => 'status',
					'ariaSort' => null,
					'lead' => false,
					'stages' => array_values(array_map(
						static fn(Stage $stage): string => $stage->value,
						array_filter(
							$column->stages,
							static fn(Stage $stage): bool => $drafts || $stage !== Stage::Draft,
						),
					)),
					'look' => $column->look,
					'fixed' => $column->fixed,
					'badges' => false,
					'searched' => false,
				];

				continue;
			}

			$label = $column->title;
			// A manually ordered top level has no column order to offer.
			$sort = $arranged ? null : $column->sort;
			$isSorted = $sort !== null && $sort->key === $urls->query->sort;
			$nextDir = $sort?->direction;
			$ariaSort = null;
			$class = '';

			if ($isSorted) {
				$ascending = $urls->query->dir === 'asc';
				$nextDir = $ascending ? 'desc' : 'asc';
				$ariaSort = $ascending ? 'ascending' : 'descending';
				$class = 'is-sorted is-' . $urls->query->dir;
			}

			$headers[] = [
				'label' => $label,
				'name' => $label,
				'url' => $sort === null
					? null
					: $urls->collection(['sort' => $sort->key, 'dir' => $nextDir, 'offset' => '']),
				'class' => trim($class . ($column->kind() === 'number' ? ' cms-list-col-number' : '')),
				'kind' => $column->kind(),
				'ariaSort' => $ariaSort,
				'lead' => !$lead,
				'stages' => [],
				'look' => $column instanceof UserColumn ? $column->look : '',
				'fixed' => false,
				'badges' => false,
				'searched' => $column->searched() !== [],
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
	 * @param list<array{label: string, name: string, url: ?string, class: string, kind: string, ariaSort: ?string, lead: bool, stages: list<string>, look: string, fixed: bool, badges: bool, searched: bool}> $headers
	 * @param list<mixed> $nodes
	 * @return list<array{
	 *     uid: string,
	 *     depth: int,
	 *     expanded: bool,
	 *     last: bool,
	 *     published: bool,
	 *     hasChildren: bool,
	 *     path: list<string>,
	 *     snippet: list<array{text: string, match: bool}>,
	 *     cells: list<array{label: string, value: string, class: string, editUrl: ?string, lead: bool, avatar: ?array{label: string, initials: string, hue: int}, avatarOnly: bool, marks: ?list<array{text: string, match: bool}>, status: ?array{stages: list<array{key: string, state: string, name: string, text: string, help: string, badge: string}>, sentence: string, look: string, fixed: bool, badges: list<array{kind: string, label: string}>}}>,
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
		?Marker $marker,
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
				'path' => array_values(array_filter((array) ($node['path'] ?? []), 'is_string')),
				'snippet' => self::snippet($node['snippet'] ?? []),
				'cells' => self::cells($node, $headers, $urls, $meta, $locale, $timezone, $marker),
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
	 * Where a search matched in a cell, null where it did not.
	 *
	 * @return ?list<array{text: string, match: bool}>
	 */
	private static function marks(Marker $marker, string $value): ?array
	{
		$segments = $marker->segments($value);

		return array_any($segments, static fn(array $segment): bool => $segment['match']) ? $segments : null;
	}

	/** @return list<array{text: string, match: bool}> */
	private static function snippet(mixed $segments): array
	{
		$snippet = [];

		foreach (is_array($segments) ? $segments : [] as $segment) {
			if (is_array($segment) && is_string($segment['text'] ?? null)) {
				$snippet[] = ['text' => $segment['text'], 'match' => (bool) ($segment['match'] ?? false)];
			}
		}

		return $snippet;
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
	 * @param list<array{label: string, name: string, url: ?string, class: string, kind: string, ariaSort: ?string, lead: bool, stages: list<string>, look: string, fixed: bool, badges: bool, searched: bool}> $headers
	 * @return list<array{label: string, value: string, class: string, editUrl: ?string, lead: bool, avatar: ?array{label: string, initials: string, hue: int}, avatarOnly: bool, marks: ?list<array{text: string, match: bool}>, status: ?array{stages: list<array{key: string, state: string, name: string, text: string, help: string, badge: string}>, sentence: string, look: string, fixed: bool, badges: list<array{kind: string, label: string}>}}>
	 */
	private static function cells(
		array $node,
		array $headers,
		CollectionUrls $urls,
		CollectionListMeta $meta,
		string $locale,
		DateTimeZone $timezone,
		?Marker $marker = null,
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
				$stages ??= self::dots($node, $locale, $timezone, fixed: true)['stages'] ?? [];
				$cells[] = [
					'label' => $label,
					'value' => '',
					'class' => 'cms-list-col-status',
					'editUrl' => null,
					'lead' => false,
					'avatar' => null,
					'avatarOnly' => false,
					'marks' => null,
					'status' => self::stagesOf($header, $stages, $header['badges'] ? self::status($node, $meta) : []),
				];

				continue;
			}

			$lead = $header === null ? $index === 0 : $header['lead'];
			$classes = [];

			if ((bool) ($column['bold'] ?? false)) {
				$classes[] = 'is-bold';
			}

			if ((bool) ($column['italic'] ?? false)) {
				$classes[] = 'is-italic';
			}

			$kind = $header['kind'] ?? 'text';

			if ($kind === 'number') {
				$classes[] = 'cms-list-col-number';
			}

			$value = $column['value'] ?? '';
			$look = $header !== null && $header['kind'] === 'user' ? $header['look'] : 'name';
			// The first column links the entry by its name alone.
			$avatar = !$lead && $look !== 'name' && $value instanceof Avatar ? $value->array() : null;

			$display = self::displayValue($value, $kind, $locale, $timezone);
			$cells[] = [
				'label' => $label,
				'value' => $display,
				'class' => implode(' ', $classes),
				'editUrl' => $lead && $uid !== '' ? $urls->edit($uid) : null,
				'lead' => $lead,
				'avatar' => $avatar,
				'avatarOnly' => $avatar !== null && $look === 'avatar',
				'marks' => $marker !== null && ($header['searched'] ?? false) ? self::marks($marker, $display) : null,
				'status' => null,
			];
		}

		return $cells;
	}

	/**
	 * A status column's share of a row's stages, one per slot of the column,
	 * up to the last that holds something unless fixed. A record's saved
	 * state fills the main version's slot; the draft slot, which a type
	 * without drafts lacks, stays empty and out of the sentence.
	 *
	 * @param array{label: string, name: string, url: ?string, class: string, kind: string, ariaSort: ?string, lead: bool, stages: list<string>, look: string, fixed: bool, badges: bool, searched: bool} $header
	 * @param list<array{key: string, state: string, name: string, text: string, help: string, badge: string}> $stages
	 * @param list<array{kind: string, label: string}> $badges
	 * @return array{stages: list<array{key: string, state: string, name: string, text: string, help: string, badge: string}>, sentence: string, look: string, fixed: bool, badges: list<array{kind: string, label: string}>}
	 */
	private static function stagesOf(array $header, array $stages, array $badges): array
	{
		$bySlot = [];

		foreach ($stages as $stage) {
			$bySlot[$stage['key'] === 'saved' ? Stage::Main->value : $stage['key']] = $stage;
		}

		$slots = [];
		$held = [];

		foreach ($header['stages'] as $slot) {
			$stage = $bySlot[$slot] ?? null;
			$held[] = $stage;
			$slots[] = $stage ?? [
				'key' => $slot,
				'state' => 'none',
				'name' => '',
				'text' => '',
				'help' => '',
				'badge' => '',
			];
		}

		$shown = $header['fixed'] ? $slots : NodeStatus::chain($slots);

		return [
			'stages' => $shown,
			'sentence' => NodeStatus::sentence(array_values(array_filter(
				array_slice($held, 0, count($shown)),
				static fn(?array $stage): bool => $stage !== null,
			))),
			'look' => $header['look'],
			'fixed' => $header['fixed'],
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
	 * The row's stages as Panel\NodeStatus derives them, for its dots: up to
	 * the last that holds something unless fixed.
	 *
	 * @param array<string, mixed> $node
	 * @return ?array{stages: list<array{key: string, state: string, name: string, text: string, help: string, badge: string}>, sentence: string}
	 */
	public static function dots(array $node, string $locale, DateTimeZone $timezone, bool $fixed = false): ?array
	{
		$facts = $node['stages'] ?? null;

		if (!is_array($facts)) {
			return null;
		}

		$draft = is_array($facts['draft'] ?? null) ? $facts['draft'] : null;
		$mine = is_array($facts['mine'] ?? null) ? $facts['mine'] : null;
		$stages = NodeStatus::of(
			drafts: (bool) ($facts['drafts'] ?? true),
			publication: (bool) ($facts['publication'] ?? true),
			published: (bool) ($node['published'] ?? false),
			edited: (string) ($facts['edited'] ?? ''),
			editor: is_string($facts['editor'] ?? null) ? $facts['editor'] : null,
			draft: $draft === null
				? null
				: [
					'changed' => (string) ($draft['changed'] ?? ''),
					'editorName' => is_string($draft['editorName'] ?? null) ? $draft['editorName'] : null,
					'revision' => is_int($draft['revision'] ?? null) ? $draft['revision'] : null,
				],
			autosave: $mine === null
				? null
				: [
					'changed' => (string) ($mine['changed'] ?? ''),
					'base' => is_int($mine['base'] ?? null) ? $mine['base'] : null,
				],
			revision: is_int($facts['revision'] ?? null) ? $facts['revision'] : null,
			routable: (bool) ($facts['routable'] ?? true),
		)->stages(NodeStatus::dates($locale, $timezone));
		$stages = $fixed ? $stages : NodeStatus::chain($stages);

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
		string $kind,
		string $locale,
		DateTimeZone $timezone,
	): string {
		$date = $kind === 'date' || $kind === 'datetime';
		$time = $kind === 'datetime';

		$numeric = is_int($value) || is_float($value) || is_string($value) && is_numeric(trim($value));

		if ($kind === 'number' && $numeric) {
			return self::formatNumber($value, $locale);
		}

		// A date object keeps its own zone, like a date field's; stored text is
		// a timestamp shown in the panel's zone.
		if ($date && $value instanceof DateTimeInterface) {
			$formatted = self::formatDate($value, $locale, $value->getTimezone() ?: $timezone, $time);

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
						$time,
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
		bool $time,
	): ?string {
		$formatter = new IntlDateFormatter(
			$locale,
			IntlDateFormatter::MEDIUM,
			$time ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE,
			$timezone,
		);
		$formatted = $formatter->format($value->getTimestamp());

		return $formatted === false ? null : $formatted;
	}

	/**
	 * Groups the integer digits and swaps in the locale's symbols, keeping
	 * every decimal of a stored string, which a float would round.
	 */
	private static function formatNumber(int|float|string $value, string $locale): string
	{
		$formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);

		if (!is_string($value)) {
			$formatted = $formatter->format($value);

			return $formatted === false ? (string) $value : $formatted;
		}

		if (preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/D', trim($value), $parts) !== 1 || strlen($parts[2]) > 15) {
			return trim($value);
		}

		$integer = $formatter->format((int) $parts[2]);
		$sign = $parts[1] === '-' ? $formatter->getSymbol(NumberFormatter::MINUS_SIGN_SYMBOL) : '';
		$fraction = ($parts[3] ?? '') === ''
			? ''
			: $formatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL) . $parts[3];

		return $integer === false ? trim($value) : $sign . $integer . $fraction;
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
