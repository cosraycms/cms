<?php

declare(strict_types=1);

namespace Cosray\Migration;

use Closure;
use Cosray\Field;
use Cosray\Uid;

final class NodeContentNormalizer
{
	private const string ZXX = Field\Field::NEUTRAL_LOCALE;

	/** @var array<class-string<Field\Field>, true> */
	private const array MEDIA_TYPES = [
		Field\File::class => true,
		Field\Image::class => true,
		Field\Video::class => true,
	];

	private readonly Field\Index $index;

	/**
	 * The optional $mediaItem callback takes over media item conversion
	 * (asset-catalog migration: `{file}` → `{uid}`). Without it the
	 * output stays byte-identical to migration 017's — downstreams that
	 * run 017 before the assets migrations depend on that.
	 *
	 * @param null|Closure(array<string, mixed>): ?array $mediaItem
	 */
	public function __construct(
		private readonly Uid $uid,
		?Field\Index $index = null,
		private readonly ?Closure $mediaItem = null,
	) {
		$this->index = $index ?? Field\Index::withDefaults();
	}

	/** @param array<string, mixed> $content */
	public function normalize(array $content): array
	{
		$result = [];

		foreach ($content as $name => $field) {
			if (!is_array($field)) {
				continue;
			}

			$result[$name] = $this->field($field);
		}

		return $result;
	}

	/** @param array<string, mixed> $data */
	private function field(array $data): array
	{
		$type = $this->fieldType($data['type'] ?? null);

		if ($type === Field\Blocks::class) {
			return $this->blocksField($data, $type);
		}

		if ($type === Field\Entries::class) {
			return $this->entriesField($data, $type);
		}

		if (isset(self::MEDIA_TYPES[$type])) {
			return $this->mediaField($data, $type, ($data['type'] ?? null) === 'picture');
		}

		$result = ['type' => $type];

		// Structured richtext carries the format envelope next to its
		// value; the keys pass through untouched (see docs/richtext-format.md).
		foreach (['format', 'version'] as $key) {
			if (array_key_exists($key, $data)) {
				$result[$key] = $data[$key];
			}
		}

		$result['value'] = $this->valueMap($data['value'] ?? null);
		$meta = $this->fieldMeta($data, ['type', 'format', 'version', 'value']);

		if ($meta !== []) {
			$result['meta'] = $meta;
		}

		return $result;
	}

	/** @param array<string, mixed> $data */
	private function blocksField(array $data, string $type): array
	{
		$value = $data['value'] ?? $data['items'] ?? [];
		$result = [
			'type' => $type,
			'value' => $this->blockValueMap($value),
		];
		$meta = $this->fieldMeta($data, ['type', 'value', 'items']);

		if ($meta !== []) {
			$result['meta'] = $meta;
		}

		return $result;
	}

	/** @param array<string, mixed> $data */
	private function entriesField(array $data, string $type): array
	{
		$value = $data['value'] ?? [];
		$result = [
			'type' => $type,
			'value' => $this->entryValueMap($value),
		];
		$meta = $this->fieldMeta($data, ['type', 'value']);

		if ($meta !== []) {
			$result['meta'] = $meta;
		}

		return $result;
	}

	/** @param array<string, mixed> $data */
	private function mediaField(array $data, string $type, bool $picture): array
	{
		$value = $data['value'] ?? $data['files'] ?? [];
		$result = [
			'type' => $type,
			'value' => $this->mediaValueMap($value, $picture),
		];
		$meta = $this->fieldMeta($data, ['type', 'value', 'files']);

		if ($meta !== []) {
			$result['meta'] = $meta;
		}

		return $result;
	}

	private function fieldType(mixed $type): string
	{
		if (is_string($type)) {
			return $this->index->resolve($type) ?? Field\Text::class;
		}

		return Field\Text::class;
	}

	private function valueMap(mixed $value): array
	{
		if (is_array($value) && $this->isLocaleMap($value)) {
			return $value;
		}

		return [self::ZXX => $value];
	}

	private function blockValueMap(mixed $value): array
	{
		if (is_array($value) && $this->isLocaleMap($value)) {
			$result = [];

			foreach ($value as $locale => $items) {
				$result[$locale] = $this->blockList($items);
			}

			return $result;
		}

		return [self::ZXX => $this->blockList($value)];
	}

	private function blockList(mixed $items): array
	{
		if (!is_array($items)) {
			return [];
		}

		$result = [];

		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$result[] = $this->block($item);
		}

		return $result;
	}

	/** @param array<string, mixed> $data */
	private function block(array $data): array
	{
		$type = is_string($data['type'] ?? null) ? $data['type'] : 'text';
		$result = ['type' => $type];

		foreach (['uid', 'width', 'colspan', 'rowspan', 'colstart', 'format', 'version'] as $key) {
			if (!array_key_exists($key, $data)) {
				continue;
			}

			$result[$key] = $data[$key];
		}

		if (in_array($type, ['image', 'images', 'video'], true)) {
			$value = $data['value'] ?? $data['files'] ?? [];
			$list = $this->mediaList($value);
			$result['value'] = $type === 'image' ? array_slice($list, 0, 1) : $list;
		} elseif ($type === 'youtube') {
			$result['value'] = $this->valueMap($data['value'] ?? $data['id'] ?? null);
		} else {
			$result['value'] = $this->valueMap($data['value'] ?? null);
		}

		$meta = $this->fieldMeta($data, [
			'type',
			'uid',
			'width',
			'colspan',
			'rowspan',
			'colstart',
			'format',
			'version',
			'value',
			'files',
		]);

		if ($meta !== []) {
			$result['meta'] = $meta;
		}

		return $result;
	}

	private function mediaValueMap(mixed $value, bool $picture): array
	{
		if (is_array($value) && $this->isLocaleMap($value)) {
			$result = [];

			foreach ($value as $locale => $items) {
				$list = $this->mediaList($items);
				$result[$locale] = $picture ? $this->selectPicture($list) : $list;
			}

			return $result;
		}

		$list = $this->mediaList($value);

		return [self::ZXX => $picture ? $this->selectPicture($list) : $list];
	}

	private function mediaList(mixed $items): array
	{
		if (!is_array($items)) {
			return [];
		}

		$result = [];

		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$converted = $this->mediaItem($item);

			if ($converted !== null) {
				$result[] = $converted;
			}
		}

		return $result;
	}

	/** @param array<string, mixed> $item */
	private function mediaItem(array $item): ?array
	{
		if ($this->mediaItem !== null) {
			return ($this->mediaItem)($item);
		}

		$result = ['file' => $item['file'] ?? ''];
		$meta = $this->fieldMeta($item, ['file']);

		if ($meta !== []) {
			$result['meta'] = $meta;
		}

		return $result;
	}

	private function selectPicture(array $items): array
	{
		if ($items === []) {
			return [];
		}

		foreach ($items as $item) {
			$file = is_string($item['file'] ?? null) ? $item['file'] : '';

			if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'webp') {
				return [$item];
			}
		}

		return [$items[0]];
	}

	private function entryValueMap(mixed $value): array
	{
		if (is_array($value) && $this->isLocaleMap($value)) {
			$result = [];

			foreach ($value as $locale => $entries) {
				$result[$locale] = $this->entryList($entries);
			}

			return $result;
		}

		return [self::ZXX => $this->entryList($value)];
	}

	private function entryList(mixed $entries): array
	{
		if (!is_array($entries)) {
			return [];
		}

		$result = [];

		foreach ($entries as $entry) {
			if (!is_array($entry)) {
				continue;
			}

			$result[] = $this->entry($entry);
		}

		return $result;
	}

	/** @param array<string, mixed> $entry */
	private function entry(array $entry): array
	{
		$fields = $entry['fields'] ?? $entry['value'] ?? [];

		return [
			'uid' => is_string($entry['uid'] ?? null) && $entry['uid'] !== ''
				? $entry['uid']
				: $this->uid->generate(),
			'type' => is_string($entry['type'] ?? null) ? $entry['type'] : '',
			'fields' => is_array($fields) ? $this->normalize($fields) : [],
		];
	}

	/** @param list<string> $skip */
	private function fieldMeta(array $data, array $skip): array
	{
		$meta = [];

		if (is_array($data['meta'] ?? null)) {
			$meta = $this->normalizeMeta($data['meta']);
		}

		foreach ($data as $key => $value) {
			if (in_array($key, $skip, true) || $key === 'meta') {
				continue;
			}

			$meta[$key] = $this->normalizeMetaLeaf($value);
		}

		return $meta;
	}

	private function normalizeMeta(array $meta): array
	{
		$result = [];

		foreach ($meta as $key => $value) {
			$result[$key] = $this->normalizeMetaLeaf($value);
		}

		return $result;
	}

	private function normalizeMetaLeaf(mixed $value): array
	{
		if (is_array($value) && $this->isLocaleMap($value)) {
			return $value;
		}

		return [self::ZXX => $value];
	}

	private function isLocaleMap(array $value): bool
	{
		if ($value === [] || array_is_list($value)) {
			return false;
		}

		foreach (array_keys($value) as $key) {
			if (!is_string($key) || !$this->isLocaleKey($key)) {
				return false;
			}
		}

		return true;
	}

	private function isLocaleKey(string $key): bool
	{
		return $key === self::ZXX || preg_match('/^[a-z]{2}(?:[-_][A-Za-z0-9]{2,8})?$/', $key) === 1;
	}
}
