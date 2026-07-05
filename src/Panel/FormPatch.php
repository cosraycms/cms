<?php

declare(strict_types=1);

namespace Cosray\Panel;

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

		return match ($name) {
			'checkbox' => $raw === '1' || $raw === 'on' || $raw === true,
			'number' => is_numeric($raw) ? (float) $raw : null,
			default => is_scalar($raw) ? (string) $raw : null,
		};
	}
}
