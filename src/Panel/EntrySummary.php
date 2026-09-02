<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Cosray\Field\Decimal;
use Cosray\Field\Image;
use Cosray\Field\Number;
use Cosray\Field\RichText;
use Cosray\Field\Text;
use Cosray\Field\Textarea;

/**
 * What a collapsed entry row shows: the first two text-like sub-fields
 * carrying content and the first image sub-field carrying an asset,
 * walking the entry type's fields in declaration order. Nothing is
 * configured; the order of the entry class's properties decides.
 */
final class EntrySummary
{
	private const int LENGTH = 60;

	public function __construct(
		public readonly ?string $primary,
		public readonly ?string $secondary,
		public readonly ?string $thumb,
		public readonly bool $hasImage,
	) {}

	/**
	 * @param array<string, mixed> $entryType the descriptor's entry type row
	 * @param array<string, mixed> $fieldsData the stored row's `fields` map
	 * @param array<string, array<string, mixed>> $assets the node's asset map
	 */
	public static function of(
		array $entryType,
		array $fieldsData,
		array $assets,
		string $locale,
	): self {
		$texts = [];
		$thumb = null;
		$hasImage = false;

		foreach ((array) ($entryType['fields'] ?? []) as $sub) {
			if (!is_array($sub) || !is_string($sub['name'] ?? null) || !is_string($sub['type'] ?? null)) {
				continue;
			}

			$type = $sub['type'];
			$data = $fieldsData[$sub['name']] ?? null;
			$value = is_array($data) && is_array($data['value'] ?? null) ? $data['value'] : [];

			if (is_a($type, Image::class, true)) {
				$hasImage = true;
				$thumb ??= self::thumb($value, $assets, $locale);

				continue;
			}

			if (count($texts) >= 2 || !self::textLike($type)) {
				continue;
			}

			$text = self::text($value, $locale, is_a($type, RichText::class, true));

			if ($text !== null) {
				$texts[] = $text;
			}
		}

		return new self($texts[0] ?? null, $texts[1] ?? null, $thumb, $hasImage);
	}

	private static function textLike(string $type): bool
	{
		foreach ([Text::class, Textarea::class, Number::class, Decimal::class, RichText::class] as $class) {
			if (is_a($type, $class, true)) {
				return true;
			}
		}

		return false;
	}

	/** @param array<string, mixed> $value */
	private static function text(array $value, string $locale, bool $richtext): ?string
	{
		foreach (self::localized($value, $locale) as $localized) {
			$text = trim((string) preg_replace('/\s+/u', ' ', self::plain($localized, $richtext)));

			if ($text === '') {
				continue;
			}

			return mb_strlen($text) > self::LENGTH
				? rtrim(mb_substr($text, 0, self::LENGTH)) . '…'
				: $text;
		}

		return null;
	}

	private static function plain(mixed $localized, bool $richtext): string
	{
		if ($richtext) {
			return is_array($localized) ? self::docText($localized) : '';
		}

		return is_scalar($localized) ? (string) $localized : '';
	}

	/**
	 * @param array<string, mixed> $value
	 * @param array<string, array<string, mixed>> $assets
	 */
	private static function thumb(array $value, array $assets, string $locale): ?string
	{
		foreach (self::localized($value, $locale) as $localized) {
			if (!is_array($localized)) {
				continue;
			}

			foreach ($localized as $item) {
				$uid = is_array($item) ? $item['uid'] ?? null : null;
				$asset = is_string($uid) ? $assets[$uid] ?? null : null;
				$url = is_array($asset) ? $asset['thumbUrl'] ?? $asset['url'] ?? null : null;

				if (is_string($url) && $url !== '') {
					return $url;
				}
			}
		}

		return null;
	}

	/**
	 * The requested locale first, then the neutral one, then whatever
	 * else carries content — a row typed in one language only still
	 * gets a summary.
	 *
	 * @param array<string, mixed> $value
	 * @return list<mixed>
	 */
	private static function localized(array $value, string $locale): array
	{
		$ordered = [];

		foreach ([$locale, 'zxx'] as $key) {
			if (array_key_exists($key, $value)) {
				$ordered[$key] = $value[$key];
			}
		}

		return array_values($ordered + $value);
	}

	/** @param array<string, mixed> $node */
	private static function docText(array $node): string
	{
		if (is_string($node['text'] ?? null)) {
			return $node['text'];
		}

		$parts = [];
		$textblock = false;

		foreach ((array) ($node['content'] ?? []) as $child) {
			if (!is_array($child)) {
				continue;
			}

			$textblock = $textblock || is_string($child['text'] ?? null);
			$parts[] = self::docText($child);
		}

		// Block nodes end in a space so adjacent paragraphs do not run
		// together; inline runs stay joined.
		return implode('', $parts) . ($textblock ? ' ' : '');
	}
}
