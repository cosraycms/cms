<?php

declare(strict_types=1);

namespace Cosray\Migration;

use Cosray\Block\Heading;
use Cosray\Block\Iframe;
use Cosray\Block\Image;
use Cosray\Block\Images;
use Cosray\Block\RichText;
use Cosray\Block\Text;
use Cosray\Block\Video;
use Cosray\Block\Youtube;
use Cosray\Field;
use Cosray\Uid;

/**
 * Reshapes legacy blocks fields into typed rows for migration
 * 000000-000031: `{type: id, colspan, rowspan, colstart, width, value,
 * meta}` becomes `{uid, type: class, layout: {span, rows, indent},
 * fields, meta?}`.
 *
 * Layouts are copied, never clamped against the field's columns — the
 * schema is unknown here and every reader clamps. Rows that already
 * carry `layout` and `fields`, blocks of unknown type and fields that
 * are not blocks pass through untouched; the report lists what was
 * converted, generated, dropped and skipped.
 */
final class BlockRowConverter
{
	private const string ZXX = Field\Field::NEUTRAL_LOCALE;

	/** The implicit column count every legacy span was measured in. */
	private const int LEGACY_COLUMNS = 12;

	/** @var array<string, class-string> Legacy type id to block class. */
	private const array TYPES = [
		'richtext' => RichText::class,
		'html' => RichText::class,
		'text' => Text::class,
		'h1' => Heading::class,
		'h2' => Heading::class,
		'h3' => Heading::class,
		'h4' => Heading::class,
		'h5' => Heading::class,
		'h6' => Heading::class,
		'image' => Image::class,
		'images' => Images::class,
		'video' => Video::class,
		'youtube' => Youtube::class,
		'iframe' => Iframe::class,
	];

	private const array ASPECT_RATIO = ['aspectRatioX', 'aspectRatioY'];
	private const array FIELD_META = ['columns', 'minCellWidth'];

	private array $report = [
		'fields' => 0,
		'blocks' => 0,
		'types' => [],
		'uidsGenerated' => 0,
		'legacyRichtext' => 0,
		'droppedMediaItems' => 0,
		'droppedItems' => 0,
		'metaKeys' => [],
		'unknownTypes' => [],
		'unresolvedFieldTypes' => [],
	];

	private string $table = '';
	private string $row = '';

	public function __construct(
		private readonly Uid $uid,
	) {}

	/**
	 * @param array<string, mixed> $content
	 * @param string $table Where the content came from, for the report.
	 * @param string $row The row's key within that table, for the report.
	 */
	public function convert(array $content, string $table = '', string $row = ''): array
	{
		$this->table = $table;
		$this->row = $row;

		return $this->walk($content, '');
	}

	public function report(): array
	{
		return $this->report;
	}

	private function walk(array $data, string $path): array
	{
		if ($this->isBlocksField($data)) {
			return $this->field($data, $path);
		}

		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$data[$key] = $this->walk($value, $path === '' ? (string) $key : "{$path}.{$key}");
			}
		}

		return $data;
	}

	private function isBlocksField(array $data): bool
	{
		$type = $data['type'] ?? null;
		$value = $data['value'] ?? null;

		if (!is_string($type) || !is_array($value)) {
			return false;
		}

		return is_a($type, Field\Blocks::class, true) || $this->looksLikeBlocks($value);
	}

	/**
	 * A locale map of lists whose items carry `colspan`: the stored
	 * field class no longer autoloads, the value still says blocks.
	 */
	private function looksLikeBlocks(array $value): bool
	{
		if ($value === [] || array_is_list($value)) {
			return false;
		}

		$found = false;

		foreach ($value as $locale => $list) {
			if (!is_string($locale) || !$this->isLocaleKey($locale)) {
				return false;
			}

			if ($list === null) {
				continue;
			}

			if (!is_array($list) || !array_is_list($list)) {
				return false;
			}

			foreach ($list as $item) {
				if (is_array($item) && array_key_exists('colspan', $item)) {
					$found = true;
				}
			}
		}

		return $found;
	}

	private function field(array $data, string $path): array
	{
		$this->report['fields']++;

		if (!is_a($data['type'], Field\Blocks::class, true)) {
			$this->count('unresolvedFieldTypes', $data['type']);
		}

		$result = [];

		foreach ($data as $key => $entry) {
			if ($key === 'value') {
				$result['value'] = $this->valueMap($entry, $path);

				continue;
			}

			if ($key === 'meta') {
				$meta = is_array($entry) ? array_diff_key($entry, array_flip(self::FIELD_META)) : [];

				if ($meta !== []) {
					$result['meta'] = $meta;
				}

				continue;
			}

			$result[$key] = $entry;
		}

		return $result;
	}

	private function valueMap(array $value, string $path): array
	{
		foreach ($value as $locale => $list) {
			if (!is_array($list)) {
				continue;
			}

			$rows = [];

			foreach ($list as $index => $item) {
				if (!is_array($item)) {
					$this->report['droppedItems']++;

					continue;
				}

				$rows[] = $this->block($item, (string) $locale, $path, (int) $index);
			}

			$value[$locale] = $rows;
		}

		return $value;
	}

	/** @param array<string, mixed> $block */
	private function block(array $block, string $locale, string $path, int $index): array
	{
		if (isset($block['layout'], $block['fields'])) {
			return $block;
		}

		$type = $block['type'] ?? null;
		$class = is_string($type) ? self::TYPES[$type] ?? null : null;

		if ($class === null) {
			$this->report['unknownTypes'][] = [
				'table' => $this->table,
				'row' => $this->row,
				'field' => $path,
				'locale' => $locale,
				'index' => $index,
				'type' => $type,
			];

			return $block;
		}

		$this->report['blocks']++;
		$this->count('types', $type);
		$uid = $block['uid'] ?? null;

		if (!is_string($uid) || $uid === '') {
			$uid = $this->uid->generate();
			$this->report['uidsGenerated']++;
		}

		$result = [
			'uid' => $uid,
			'type' => $class,
			'layout' => $this->layout($block),
			'fields' => $this->fields($type, $block, $locale),
		];
		$meta = $this->blockMeta($type, is_array($block['meta'] ?? null) ? $block['meta'] : []);

		if ($meta !== []) {
			$result['meta'] = $meta;
		}

		return $result;
	}

	/**
	 * Values below the range no schema could accept are floored; the
	 * field's real bounds are applied by the readers and the shape.
	 *
	 * @return array{span: int, rows: int, indent: int}
	 */
	private function layout(array $block): array
	{
		$colstart = $this->int($block['colstart'] ?? null);

		return [
			'span' => max(1, $this->int($block['colspan'] ?? null) ?? self::LEGACY_COLUMNS),
			'rows' => max(1, $this->int($block['rowspan'] ?? null) ?? 1),
			'indent' => $colstart === null ? 0 : max(0, $colstart - 1),
		];
	}

	/** @return array<string, array> */
	private function fields(string $type, array $block, string $locale): array
	{
		$value = $block['value'] ?? null;

		return match ($type) {
			'richtext', 'html' => ['text' => $this->richtext($block, $locale)],
			'text' => ['text' => $this->scalar(Field\Textarea::class, $value, $locale)],
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => [
				'text' => $this->scalar(Field\Text::class, $value, $locale),
				'level' => ['type' => Field\Option::class, 'value' => [self::ZXX => substr($type, 1)]],
			],
			'image' => ['image' => $this->media(Field\Image::class, $value, $locale, 1)],
			'images' => ['images' => $this->media(Field\Image::class, $value, $locale, null)],
			'video' => ['video' => $this->media(Field\Video::class, $value, $locale, 1)],
			'youtube' => ['video' => $this->youtube($block, $locale)],
			'iframe' => ['code' => $this->scalar(Field\Iframe::class, $value, $locale)],
		};
	}

	/**
	 * The envelope moves with the document. A block without one is
	 * legacy HTML the richtext migration has not seen; it keeps its
	 * markless value so that migration still recognizes it.
	 */
	private function richtext(array $block, string $locale): array
	{
		$result = ['type' => Field\RichText::class];

		if (is_string($block['format'] ?? null) && $block['format'] !== '') {
			$result['format'] = $block['format'];
			$result['version'] = $block['version'] ?? null;
		} else {
			$this->report['legacyRichtext']++;
		}

		$result['value'] = [self::ZXX => $this->pick($block['value'] ?? null, $locale)];

		return $result;
	}

	private function youtube(array $block, string $locale): array
	{
		$result = [
			'type' => Field\Youtube::class,
			'value' => [self::ZXX => $this->pick($block['value'] ?? null, $locale)],
		];
		$meta = is_array($block['meta'] ?? null) ? $block['meta'] : [];

		foreach (self::ASPECT_RATIO as $key) {
			if (array_key_exists($key, $meta)) {
				$result['meta'][$key] = $meta[$key];
			}
		}

		return $result;
	}

	private function scalar(string $class, mixed $value, string $locale): array
	{
		return ['type' => $class, 'value' => [self::ZXX => $this->pick($value, $locale)]];
	}

	private function media(string $class, mixed $value, string $locale, ?int $limit): array
	{
		$items = $this->pick($value, $locale);
		$items = is_array($items) ? array_values(array_filter($items, is_array(...))) : [];

		if ($limit !== null && count($items) > $limit) {
			$this->report['droppedMediaItems'] += count($items) - $limit;
			$items = array_slice($items, 0, $limit);
		}

		return ['type' => $class, 'value' => [self::ZXX => $items]];
	}

	/**
	 * A block's value: lists (media items) as they are; of a locale map
	 * the neutral entry, else the entry of the list's own locale, else
	 * the first one — a sub-field of a per-locale or untranslated list
	 * is untranslated.
	 */
	private function pick(mixed $value, string $locale): mixed
	{
		if (!is_array($value)) {
			return $value;
		}

		if ($value === []) {
			return null;
		}

		if (array_is_list($value)) {
			return $value;
		}

		if (array_key_exists(self::ZXX, $value)) {
			return $value[self::ZXX];
		}

		return array_key_exists($locale, $value) ? $value[$locale] : reset($value);
	}

	/**
	 * Block meta minus the YouTube aspect ratio, which moved into the
	 * field, and minus empty entries, so the report counts real uses.
	 */
	private function blockMeta(string $type, array $meta): array
	{
		if ($type === 'youtube') {
			$meta = array_diff_key($meta, array_flip(self::ASPECT_RATIO));
		}

		$result = [];

		foreach ($meta as $key => $value) {
			if ($this->isEmpty($value)) {
				continue;
			}

			$result[$key] = $value;
			$this->count('metaKeys', (string) $key);
		}

		return $result;
	}

	private function isEmpty(mixed $value): bool
	{
		if (!is_array($value)) {
			return $value === null || $value === '';
		}

		foreach ($value as $entry) {
			if (!$this->isEmpty($entry)) {
				return false;
			}
		}

		return true;
	}

	private function int(mixed $value): ?int
	{
		return is_int($value) || is_string($value) && is_numeric($value) || is_float($value) ? (int) $value : null;
	}

	private function count(string $bucket, string $key): void
	{
		$this->report[$bucket][$key] = ($this->report[$bucket][$key] ?? 0) + 1;
	}

	private function isLocaleKey(string $key): bool
	{
		return $key === self::ZXX || preg_match('/^[a-z]{2}(?:[-_][A-Za-z0-9]{2,8})?$/', $key) === 1;
	}
}
