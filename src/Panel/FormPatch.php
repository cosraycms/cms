<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Closure;
use Cosray\Block\Layout;
use Cosray\Uid;

/**
 * Patches stored node content with submitted editor form data.
 *
 * The form is a per-field patch, never a reconstruction: only fields the
 * form actually carries are replaced, unknown keys inside the stored
 * content survive untouched. Primitive leaves are cast according to the
 * field's control descriptor; rich fields submit their complete value
 * (and optionally meta) as one JSON string under the [json] key.
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

		if (is_array($value)) {
			$stored = is_array($entry['value'] ?? null) ? $entry['value'] : [];

			foreach ($value as $locale => $raw) {
				$stored[$locale] = $this->cast($control, $raw, $stored[$locale] ?? null);
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

	private function cast(array $control, mixed $raw, mixed $stored): mixed
	{
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

				$result[$key] = $this->cast($sub['control'] ?? [], $raw[$key], $result[$key] ?? null);
			}

			return $result;
		}

		if ($name === 'repeater') {
			// Lists are replaced wholesale; index gaps left by removed
			// rows are normalized away.
			$item = $props['item'] ?? [];

			return array_map(
				fn(mixed $rawItem): mixed => $this->cast($item, $rawItem, null),
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
			'checkbox' => $raw === '1' || $raw === 'on' || $raw === true,
			'number' => is_numeric($raw) ? (float) $raw : null,
			default => is_scalar($raw) ? (string) $raw : null,
		};
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
	 * Block rows add the layout — ints clamped into the field's grid, so
	 * a stored out-of-range value the editor loaded saves back clamped,
	 * where the shape would reject it — and the block meta map, patched
	 * like a field's meta against the descriptor's meta group.
	 */
	private function blocks(array $props, array $rows, array $stored): array
	{
		$columns = is_int($props['columns'] ?? null) && $props['columns'] > 0 ? $props['columns'] : 1;
		$min = is_int($props['min'] ?? null) && $props['min'] > 0 ? min($props['min'], $columns) : 1;
		$metaControl = is_array($props['meta'] ?? null) ? $props['meta'] : null;

		return $this->rows(
			self::rowTypes($props['blockTypes'] ?? []),
			$rows,
			$stored,
			function (array $storedRow, array $row, string $uid, string $type, array $fields) use (
				$columns,
				$min,
				$metaControl,
			): array {
				$layout = [
					...(is_array($storedRow['layout'] ?? null) ? $storedRow['layout'] : []),
					...(is_array($row['layout'] ?? null) ? $row['layout'] : []),
				];
				$result = [
					...$storedRow,
					'uid' => $uid,
					'type' => $type,
					'layout' => Layout::normalize($layout, $columns, $min)->array(),
					'fields' => $fields,
				];

				if ($metaControl !== null && is_array($row['meta'] ?? null)) {
					$result['meta'] = $this->meta(
						$metaControl,
						is_array($storedRow['meta'] ?? null) ? $storedRow['meta'] : [],
						$row['meta'],
					);
				}

				return $result;
			},
		);
	}

	/**
	 * Rows are replaced wholesale like a repeater, but each row's fields
	 * are patched like a group: rows are matched to their stored
	 * counterpart by uid, so unknown keys survive edits and reorders.
	 * `$build` assembles the row from the matched stored row (empty when
	 * the type changed), the submitted row, the uid and the patched fields.
	 *
	 * @param array<string, array> $types row type descriptors keyed by class
	 * @param Closure(array, array, string, string, array): array $build
	 */
	private function rows(array $types, array $rows, array $stored, Closure $build): array
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

			if (!is_string($type) || !isset($types[$type])) {
				continue;
			}

			$uid = $row['uid'] ?? null;

			if (!is_string($uid) || $uid === '') {
				// The client fills fresh uids on stamped rows; this is the
				// safety net for rows arriving without one.
				$uid = $this->uid->generate();
			}

			$storedRow = $byUid[$uid] ?? [];

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
