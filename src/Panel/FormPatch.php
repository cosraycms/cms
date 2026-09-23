<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Closure;
use Cosray\Block\Layout;
use Cosray\Block\Placement;
use Cosray\DateTime\Codec;
use Cosray\Field\Blocks;
use Cosray\Field\Field;
use Cosray\Uid;
use DateTimeZone;

/**
 * Patches stored node content with submitted editor form data.
 *
 * The form is a per-field patch, never a reconstruction: only fields the
 * form actually carries are replaced, unknown keys inside the stored
 * content survive untouched. Primitive leaves are cast according to the
 * field's control descriptor; rich fields submit their complete value
 * (and optionally meta) as one JSON string under the [json] key.
 *
 * An immutable field is never patched. The form renders it read-only, so
 * whatever the submission carries for it has no authority.
 */
final class FormPatch
{
	/** @param list<array> $fields field property payloads incl. control descriptors */
	public function __construct(
		private readonly array $fields,
		private readonly Uid $uid = new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13),
	) {}

	public function content(array $stored, array $submitted): array
	{
		foreach ($this->fields as $field) {
			$name = $field['name'] ?? null;

			if (!is_string($name) || !is_array($submitted[$name] ?? null)) {
				continue;
			}

			if ($field['immutable'] ?? false) {
				continue;
			}

			$entry = $stored[$name] ?? ['type' => $field['type'] ?? null, 'value' => []];
			$patched = $this->entry(
				$field['control'] ?? [],
				$field['metaControl'] ?? null,
				$entry,
				$submitted[$name],
			);

			if ($patched !== null) {
				$stored[$name] = $patched;
			}
		}

		return $stored;
	}

	private function entry(array $control, ?array $metaControl, array $entry, array $submitted): ?array
	{
		$json = $submitted['json'] ?? null;

		if (is_string($json)) {
			$decoded = json_decode($json, true);

			if (!is_array($decoded)) {
				return null;
			}

			if (array_key_exists('value', $decoded)) {
				$entry['value'] = $decoded['value'];
			}

			if (array_key_exists('meta', $decoded)) {
				$entry['meta'] = $decoded['meta'];
			}

			// Format envelope of structured richtext values.
			foreach (['format', 'version'] as $key) {
				if (isset($decoded[$key])) {
					$entry[$key] = $decoded[$key];
				}
			}

			return $entry;
		}

		$changed = false;
		$value = $submitted['value'] ?? null;

		// A blocks value keeps the grid it was placed on; its rows are read
		// against that count, not the field's default.
		if (($control['name'] ?? null) === 'blocks') {
			$default = is_int($control['props']['columns'] ?? null) ? $control['props']['columns'] : 1;
			$columns = Blocks::storedColumns(
				is_numeric($submitted['columns'] ?? null) ? (int) $submitted['columns'] : null,
				Blocks::storedColumns($entry['columns'] ?? null, $default),
			);
			$control['props']['columns'] = $columns;
			$entry['columns'] = $columns;
		}

		if (is_array($value)) {
			$stored = is_array($entry['value'] ?? null) ? $entry['value'] : [];
			$timezone = $this->timezone($entry);

			foreach ($value as $locale => $raw) {
				$stored[$locale] = $this->cast($control, $raw, $stored[$locale] ?? null, $timezone);
			}

			$entry['value'] = $stored;
			$changed = true;
		}

		$meta = $submitted['meta'] ?? null;

		if (is_array($meta) && is_array($metaControl)) {
			$entry['meta'] = $this->meta(
				$metaControl,
				is_array($entry['meta'] ?? null) ? $entry['meta'] : [],
				$meta,
			);
			$changed = true;
		}

		return $changed ? $entry : null;
	}

	/**
	 * Replace the meta entries the metaControl group knows; unknown
	 * stored meta keys survive.
	 */
	private function meta(array $metaControl, array $stored, array $submitted): array
	{
		foreach ($metaControl['props']['fields'] ?? [] as $sub) {
			$key = $sub['key'] ?? null;

			if (!is_string($key) || !is_array($submitted[$key] ?? null)) {
				continue;
			}

			$map = is_array($stored[$key] ?? null) ? $stored[$key] : [];

			foreach ($submitted[$key] as $locale => $raw) {
				$map[$locale] = $this->cast($sub['control'] ?? [], $raw, $map[$locale] ?? null);
			}

			$stored[$key] = $map;
		}

		return $stored;
	}

	private function cast(
		array $control,
		mixed $raw,
		mixed $stored,
		?DateTimeZone $timezone = null,
	): mixed {
		$name = $control['name'] ?? '';
		$props = $control['props'] ?? [];

		if ($name === 'group') {
			// Replace only the keys the descriptor knows; anything else
			// stored inside the group survives.
			$result = is_array($stored) ? $stored : [];

			foreach ($props['fields'] ?? [] as $sub) {
				$key = $sub['key'] ?? null;

				if (!is_string($key) || !is_array($raw) || !array_key_exists($key, $raw)) {
					continue;
				}

				$result[$key] = $this->cast(
					$sub['control'] ?? [],
					$raw[$key],
					$result[$key] ?? null,
					$timezone,
				);
			}

			return $result;
		}

		if ($name === 'repeater') {
			// Lists are replaced wholesale; index gaps left by removed
			// rows are normalized away.
			$item = $props['item'] ?? [];

			return array_map(
				fn(mixed $rawItem): mixed => $this->cast($item, $rawItem, null, $timezone),
				is_array($raw) ? array_values($raw) : [],
			);
		}

		if ($name === 'entries' || $name === 'blocks') {
			$rows = is_array($raw) ? array_values($raw) : [];
			$stored = is_array($stored) ? $stored : [];

			return $name === 'entries'
				? $this->entries($props, $rows, $stored)
				: $this->blocks($props, $rows, $stored);
		}

		return match ($name) {
			'checkbox' => $props['nullable'] ?? false
				? match ($raw) {
					'', null => null,
					'1', true => true,
					'0', false => false,
					default => $raw,
				}
				: $raw === '1' || $raw === 'on' || $raw === true,
			'number' => is_numeric($raw) ? (float) $raw : null,
			'datetime' => $this->datetime($raw, $timezone ?? Codec::utc()),
			default => is_scalar($raw) ? (string) $raw : null,
		};
	}

	private function datetime(mixed $raw, DateTimeZone $timezone): mixed
	{
		if (!is_scalar($raw)) {
			return null;
		}

		$value = (string) $raw;

		return Codec::fromInput($value, $timezone) ?? Codec::normalize($value) ?? $value;
	}

	private function timezone(array $entry): DateTimeZone
	{
		$timezone = $entry['meta']['timezone'] ?? null;

		if (is_array($timezone)) {
			$timezone = $timezone[Field::NEUTRAL_LOCALE] ?? null;
		}

		return Codec::timezone($timezone) ?? Codec::utc();
	}

	private function entries(array $props, array $rows, array $stored): array
	{
		return $this->rows(
			self::rowTypes($props['entryTypes'] ?? []),
			$rows,
			$stored,
			static fn(array $storedRow, array $row, string $uid, string $type, array $fields): array => [
				...$storedRow,
				'uid' => $uid,
				'type' => $type,
				'fields' => $fields,
			],
		);
	}

	/**
	 * Block rows add the layout — ints clamped into the area the block is
	 * laid out on, the field's grid or the split it sits in, so a stored
	 * out-of-range value the editor loaded saves back clamped, where the
	 * shape would reject it — and the block meta map, patched like a
	 * field's meta against the descriptor's meta group. Blocks are matched
	 * by uid against every stored block, so one the editor moved into or
	 * out of a split keeps whatever the form does not carry.
	 */
	private function blocks(array $props, array $rows, array $stored): array
	{
		$columns = is_int($props['columns'] ?? null) && $props['columns'] > 0 ? $props['columns'] : 1;
		$min = is_int($props['min'] ?? null) && $props['min'] > 0 ? min($props['min'], $columns) : 1;
		$metaControl = is_array($props['meta'] ?? null) ? $props['meta'] : null;
		$types = self::rowTypes($props['blockTypes'] ?? []);
		$known = $stored;

		foreach ($stored as $storedRow) {
			if (is_array($storedRow) && is_array($storedRow['blocks'] ?? null)) {
				array_push($known, ...array_values($storedRow['blocks']));
			}
		}

		// The blocks of a split have no position, not even one stored from
		// before they were moved into it.
		$block = fn(int $columns, int $rowspan, bool $part = false): Closure => fn(
			array $storedRow,
			array $row,
			string $uid,
			string $type,
			array $fields,
		): array => $this->withMeta(
			[
				...$storedRow,
				'uid' => $uid,
				'type' => $type,
				'layout' => self::area(
					Layout::normalize(self::layout($storedRow, $row), $columns, $min, $rowspan),
					$part,
				),
				'fields' => $fields,
			],
			$storedRow,
			$row,
			$metaControl,
		);

		// A row that arrives without a position gets one below the others.
		return Placement::rows(
			$this->rows(
				$types,
				$rows,
				$known,
				$block($columns, Layout::MAX_ROWSPAN),
				function (array $storedRow, array $row, string $uid) use (
					$types,
					$known,
					$block,
					$columns,
					$min,
					$metaControl,
				): ?array {
					$layout = Layout::normalize(self::layout($storedRow, $row), $columns, $min);
					$blocks = $this->rows(
						$types,
						array_values($row['blocks']),
						$known,
						$block($layout->colspan, $layout->rowspan, part: true),
					);

					// A split holds two blocks at least; the one left takes its place.
					if (count($blocks) < 2) {
						return $blocks === [] ? null : [...$blocks[0], 'layout' => $layout->array()];
					}

					return $this->withMeta(
						[...$storedRow, 'uid' => $uid, 'layout' => $layout->array(), 'blocks' => $blocks],
						$storedRow,
						$row,
						$metaControl,
					);
				},
			),
			$columns,
			$min,
		);
	}

	/** @return array{colspan: int, rowspan: int, col?: int, row?: int} */
	private static function area(Layout $layout, bool $part): array
	{
		return $part ? new Layout($layout->colspan, $layout->rowspan)->array() : $layout->array();
	}

	/** The stored layout with the submitted dimensions over it. */
	private static function layout(array $storedRow, array $row): array
	{
		return [
			...(is_array($storedRow['layout'] ?? null) ? $storedRow['layout'] : []),
			...(is_array($row['layout'] ?? null) ? $row['layout'] : []),
		];
	}

	private function withMeta(array $result, array $storedRow, array $row, ?array $metaControl): array
	{
		if ($metaControl !== null && is_array($row['meta'] ?? null)) {
			$result['meta'] = $this->meta(
				$metaControl,
				is_array($storedRow['meta'] ?? null) ? $storedRow['meta'] : [],
				$row['meta'],
			);
		}

		return $result;
	}

	/**
	 * Rows are replaced wholesale like a repeater, but each row's fields
	 * are patched like a group: rows are matched to their stored
	 * counterpart by uid, so unknown keys survive edits and reorders.
	 * `$build` assembles the row from the matched stored row (empty when
	 * the type changed), the submitted row, the uid and the patched fields.
	 * A row without a type carrying `blocks` goes to `$split` when one is
	 * given, with its stored counterpart unless that one is a block; a
	 * null from it drops the row.
	 *
	 * @param array<string, array> $types row type descriptors keyed by class
	 * @param Closure(array, array, string, string, array): array $build
	 * @param ?Closure(array, array, string): ?array $split
	 */
	private function rows(array $types, array $rows, array $stored, Closure $build, ?Closure $split = null): array
	{
		$byUid = [];

		foreach ($stored as $storedRow) {
			$uid = is_array($storedRow) ? $storedRow['uid'] ?? null : null;

			if (is_string($uid) && $uid !== '') {
				$byUid[$uid] = $storedRow;
			}
		}

		$result = [];

		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$type = $row['type'] ?? null;
			$isSplit = $split !== null && $type === null && is_array($row['blocks'] ?? null);

			if (!$isSplit && (!is_string($type) || !isset($types[$type]))) {
				continue;
			}

			$uid = $row['uid'] ?? null;

			if (!is_string($uid) || $uid === '') {
				// The client fills fresh uids on stamped rows; this is the
				// safety net for rows arriving without one.
				$uid = $this->uid->generate();
			}

			$storedRow = $byUid[$uid] ?? [];

			if ($isSplit) {
				$built = $split(isset($storedRow['type']) ? [] : $storedRow, $row, $uid);

				if ($built !== null) {
					$result[] = $built;
				}

				continue;
			}

			if (($storedRow['type'] ?? null) !== $type) {
				$storedRow = [];
			}

			$fields = is_array($storedRow['fields'] ?? null) ? $storedRow['fields'] : [];
			$submitted = is_array($row['fields'] ?? null) ? $row['fields'] : [];

			foreach ($types[$type]['fields'] ?? [] as $sub) {
				$subName = $sub['name'] ?? null;

				if (!is_string($subName) || !is_array($submitted[$subName] ?? null)) {
					continue;
				}

				$entry = is_array($fields[$subName] ?? null)
					? $fields[$subName]
					: ['type' => $sub['type'] ?? null, 'value' => []];
				$patched = $this->entry(
					is_array($sub['control'] ?? null) ? $sub['control'] : [],
					is_array($sub['metaControl'] ?? null) ? $sub['metaControl'] : null,
					$entry,
					$submitted[$subName],
				);

				if ($patched !== null) {
					$fields[$subName] = $patched;
				}
			}

			$result[] = $build($storedRow, $row, $uid, $type, $fields);
		}

		return $result;
	}

	/** @return array<string, array> */
	private static function rowTypes(mixed $types): array
	{
		$result = [];

		foreach (is_array($types) ? $types : [] as $type) {
			if (is_array($type) && is_string($type['type'] ?? null)) {
				$result[$type['type']] = $type;
			}
		}

		return $result;
	}
}
