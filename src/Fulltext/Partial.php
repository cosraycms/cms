<?php

declare(strict_types=1);

namespace Cosray\Fulltext;

/**
 * The words of a full-text query that may also match inside longer words.
 *
 * A word is still required, or excluded with a leading `-`, as in the query
 * itself. Quoted phrases ask for exact wording, so a query containing one
 * gets no partial matching; `OR` is read as an ordinary separator, which only
 * narrows the additional matches.
 */
final class Partial
{
	/** Shorter words would match inside too many unrelated ones. */
	public const MIN_LENGTH = 3;

	private const MAX_WORDS = 8;

	/** The least score of a partial match, below a single whole-word match in body text (`0.1`) … */
	public const SCORE = 0.05;

	/** … plus up to this much as its words appear in the title, below a whole-word title match (`1.0`). */
	public const TITLE_SCORE = 0.45;

	/**
	 * @return ?list<array{word: string, pattern: string, negated: bool, inside: bool}>
	 *         Null when partial matching does not apply to the query.
	 */
	public static function words(string $query): ?array
	{
		if (str_contains($query, '"')) {
			return null;
		}

		$words = [];

		foreach (preg_split('/\s+/u', $query, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
			if (strcasecmp($token, 'or') === 0) {
				continue;
			}

			$negated = str_starts_with($token, '-');
			$word = preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $token) ?? '';

			if ($word === '') {
				continue;
			}

			$words[] = [
				'word' => $word,
				'pattern' => '%' . addcslashes($word, '\\%_') . '%',
				'negated' => $negated,
				'inside' => mb_strlen($word) >= self::MIN_LENGTH,
			];
		}

		$required = array_filter($words, static fn(array $word): bool => !$word['negated']);

		return $required === [] || count($words) > self::MAX_WORDS ? null : $words;
	}
}
