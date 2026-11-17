<?php

declare(strict_types=1);

namespace Cosray\Fulltext;

use Transliterator;

/**
 * Finds the terms of a query in a text by the rules of the search: case-
 * and accent-insensitively, words inside longer ones where a term allows
 * it. A match marks the whole words it touches, as snippets do.
 */
final class Marker
{
	private static ?Transliterator $fold = null;

	public function __construct(
		private readonly Query $query,
	) {}

	/** Whether two texts read alike to the search, as `Café` and `cafe`. */
	public static function same(string $a, string $b): bool
	{
		$normal = static fn(string $text): string => trim(preg_replace('/\s+/u', ' ', self::fold($text)[1]) ?? '');

		return $normal($a) === $normal($b);
	}

	public function contains(string $text, Term $term): bool
	{
		return preg_match($this->pattern($term), self::fold($text)[1]) === 1;
	}

	/**
	 * The text cut where the query's required terms match.
	 *
	 * @return list<array{text: string, match: bool}>
	 */
	public function segments(string $text): array
	{
		[$chars, $folded, $owners] = self::fold($text);
		$marked = array_fill(0, count($chars), false);

		foreach ($this->query->groups as $group) {
			foreach ($group as $term) {
				if ($term->negated) {
					continue;
				}

				preg_match_all($this->pattern($term), $folded, $matches, PREG_OFFSET_CAPTURE);

				foreach ($matches[0] as [$match, $offset]) {
					if ($match === '') {
						continue;
					}

					$start = $owners[$offset];
					$end = $owners[$offset + strlen($match) - 1] + 1;

					while ($start > 0 && self::wordy($chars[$start - 1])) {
						$start--;
					}

					while ($end < count($chars) && self::wordy($chars[$end])) {
						$end++;
					}

					for ($i = $start; $i < $end; $i++) {
						$marked[$i] = true;
					}
				}
			}
		}

		$segments = [];
		$run = '';
		$match = null;

		foreach ($chars as $i => $char) {
			if ($match !== null && $match !== $marked[$i]) {
				$segments[] = ['text' => $run, 'match' => $match];
				$run = '';
			}

			$run .= $char;
			$match = $marked[$i];
		}

		if ($match !== null) {
			$segments[] = ['text' => $run, 'match' => $match];
		}

		return $segments;
	}

	private function pattern(Term $term): string
	{
		$quoted = preg_replace('/\s+/u', '\s+', preg_quote(trim(self::fold($term->text)[1]), '/')) ?? '';

		return $term->inside ? "/{$quoted}/u" : "/(?<![\\p{L}\\p{N}]){$quoted}(?![\\p{L}\\p{N}])/u";
	}

	private static function wordy(string $char): bool
	{
		return preg_match('/^[\p{L}\p{N}]$/u', $char) === 1;
	}

	/**
	 * The text folded as the database's `fulltext_fold()` folds it, with the
	 * character each byte of the folded text came from.
	 *
	 * @return array{0: list<string>, 1: string, 2: list<int>}
	 */
	private static function fold(string $text): array
	{
		self::$fold ??= Transliterator::create('Fullwidth-Halfwidth; Latin-ASCII; Lower()');
		$chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
		$folded = '';
		$owners = [];

		foreach ($chars as $i => $char) {
			$part = self::$fold?->transliterate($char);
			$part = is_string($part) ? $part : mb_strtolower($char);
			$folded .= $part;
			array_push($owners, ...array_fill(0, strlen($part), $i));
		}

		return [$chars, $folded, $owners];
	}
}
