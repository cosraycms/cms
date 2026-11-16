<?php

declare(strict_types=1);

namespace Cosray\Text;

use Cosray\Exception\RuntimeException;

/**
 * A text of stored content: the string of a text field, or a richtext
 * paragraph or heading, whose inline runs read as one text.
 *
 * @internal
 */
final class Unit
{
	/** Richtext nodes whose inline content forms one unit. */
	public const array TEXTBLOCKS = ['paragraph', 'heading'];

	/** The text a stored value reads as, or null for a value that is no unit. */
	public static function text(mixed $value): ?string
	{
		if (is_string($value)) {
			return $value;
		}

		if (!is_array($value) || !in_array($value['type'] ?? null, self::TEXTBLOCKS, true)) {
			return null;
		}

		$text = '';

		foreach (self::inline($value) as $node) {
			$text .= self::runText($node);
		}

		return $text;
	}

	/**
	 * The value with the code points from `$start` to `$end` of its text
	 * replaced. In richtext the replacement takes the marks of the run where
	 * the range starts; runs inside the range go, images stay.
	 */
	public static function splice(mixed $value, int $start, int $end, string $replacement): mixed
	{
		$text = self::text($value) ?? throw new RuntimeException('The value is no text unit');

		if ($start < 0 || $end < $start || $end > mb_strlen($text)) {
			throw new RuntimeException("The range {$start}–{$end} lies outside the text");
		}

		if (is_string($value)) {
			return mb_substr($value, 0, $start) . $replacement . mb_substr($value, $end);
		}

		assert(is_array($value), 'text() accepts only strings and textblocks');
		$before = [];
		$after = [];
		$marks = null;
		$found = false;
		$from = 0;

		foreach (self::inline($value) as $node) {
			$length = mb_strlen(self::runText($node));
			$to = $from + $length;

			if (($node['type'] ?? null) === 'text') {
				$string = (string) $node['text'];
				$nodeMarks = $node['marks'] ?? null;

				if ($from < $start) {
					$before[] = self::run(mb_substr($string, 0, $start - $from), $nodeMarks);
				}

				if ($to > $end) {
					$after[] = self::run(mb_substr($string, max(0, $end - $from)), $nodeMarks);
				}

				// The run the range starts in, else the one it follows.
				if (!$found && ($from <= $start && $start < $to || $to <= $start)) {
					$marks = $nodeMarks;
					$found = $from <= $start && $start < $to;
				}
			} elseif ($length === 0) {
				// Images have no text and stay.
				if ($from <= $start) {
					$before[] = $node;
				} else {
					$after[] = $node;
				}
			} elseif ($to <= $start) {
				$before[] = $node;
			} elseif ($from >= $end) {
				$after[] = $node;
			}

			$from = $to;
		}

		$value['content'] = self::merge([...$before, self::run($replacement, $marks), ...$after]);

		if ($value['content'] === []) {
			unset($value['content']);
		}

		return $value;
	}

	/**
	 * @param array<mixed> $block
	 * @return list<array<mixed>>
	 */
	private static function inline(array $block): array
	{
		$content = $block['content'] ?? [];

		return array_values(array_filter(is_array($content) ? $content : [], is_array(...)));
	}

	/** @param array<mixed> $node */
	private static function runText(array $node): string
	{
		return match ($node['type'] ?? null) {
			'text' => is_string($node['text'] ?? null) ? $node['text'] : '',
			'hardBreak' => "\n",
			default => '',
		};
	}

	/** @return array<mixed> */
	private static function run(string $text, mixed $marks): array
	{
		$run = ['type' => 'text', 'text' => $text];

		if (is_array($marks) && $marks !== []) {
			$run['marks'] = $marks;
		}

		return $run;
	}

	/**
	 * Drops empty text runs and joins neighbours with equal marks, as the
	 * richtext normalizer stores them.
	 *
	 * @param list<array<mixed>> $runs
	 * @return list<array<mixed>>
	 */
	private static function merge(array $runs): array
	{
		$merged = [];

		foreach ($runs as $run) {
			if (($run['type'] ?? null) === 'text') {
				if ($run['text'] === '') {
					continue;
				}

				$last = array_key_last($merged);

				if (
					$last !== null
					&& ($merged[$last]['type'] ?? null) === 'text'
					&& ($merged[$last]['marks'] ?? null) === ($run['marks'] ?? null)
				) {
					$merged[$last]['text'] .= $run['text'];

					continue;
				}
			}

			$merged[] = $run;
		}

		return $merged;
	}
}
