<?php

declare(strict_types=1);

namespace Cosray\Migration;

use Cosray\Block\RichText;
use Cosray\Block\Text;
use Cosray\Field;
use Cosray\Richtext\Envelope;

/**
 * Plain text blocks turned into rich text blocks, wherever content holds
 * them: on a field's grid, in a split or in a blocks field nested in a
 * block. A run of lines between blank lines becomes a paragraph and each
 * single line break a hard break, so the words and their breaks stay; a
 * paragraph now comes wrapped in `<p>` where the plain text stood bare
 * with `<br>`s. The block keeps its uid, layout and settings. Content
 * without plain text blocks comes back as it was, so the conversion is
 * safe to run again.
 */
final class TextBlocks
{
	/**
	 * @param array<array-key, mixed> $data
	 * @return array<array-key, mixed>
	 */
	public static function content(array $data): array
	{
		if (($data['type'] ?? null) === Text::class && is_array($data['fields'] ?? null)) {
			return self::block($data);
		}

		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$data[$key] = self::content($value);
			}
		}

		return $data;
	}

	/**
	 * A plain text as a rich text document; none for one that holds
	 * nothing but whitespace, as an untouched rich text holds none.
	 *
	 * @return array{type: 'doc', content: list<array<string, mixed>>}|null
	 */
	public static function doc(string $text): ?array
	{
		$text = trim(str_replace(["\r\n", "\r"], "\n", $text));

		if ($text === '') {
			return null;
		}

		$paragraphs = [];

		foreach (preg_split('/\n[ \t]*\n\s*/', $text) ?: [] as $paragraph) {
			$inline = [];

			foreach (explode("\n", $paragraph) as $index => $line) {
				if ($index > 0) {
					$inline[] = ['type' => 'hardBreak'];
				}

				if ($line !== '') {
					$inline[] = ['type' => 'text', 'text' => $line];
				}
			}

			$paragraphs[] = ['type' => 'paragraph', 'content' => $inline];
		}

		return ['type' => 'doc', 'content' => $paragraphs];
	}

	/**
	 * @param array<array-key, mixed> $row
	 * @return array<array-key, mixed>
	 */
	private static function block(array $row): array
	{
		$text = $row['fields']['text'] ?? null;
		$values = is_array($text) && is_array($text['value'] ?? null) ? $text['value'] : [];

		$row['type'] = RichText::class;
		$row['fields']['text'] = [
			'type' => Field\RichText::class,
			'value' => array_map(
				static fn(mixed $value): ?array => is_string($value) ? self::doc($value) : null,
				$values,
			),
			'format' => Envelope::FORMAT,
			'version' => Envelope::VERSION,
		];

		return $row;
	}
}
