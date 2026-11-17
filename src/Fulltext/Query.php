<?php

declare(strict_types=1);

namespace Cosray\Fulltext;

/**
 * A search query read as `websearch_to_tsquery()` reads it: every word and
 * quoted phrase is required, a leading `-` excludes one, and `OR` joins its
 * neighbours into a group of alternatives. Groups are all required.
 */
final readonly class Query
{
	/** Shorter words would match inside too many unrelated ones. */
	public const MIN_LENGTH = 3;

	/** A longer query matches whole words only. */
	public const MAX_TERMS = 8;

	/** @param list<non-empty-list<Term>> $groups */
	private function __construct(
		public array $groups,
	) {}

	/**
	 * Words of at least `$inside` characters also match inside longer
	 * words, unless the query holds more than MAX_TERMS words and phrases.
	 */
	public static function parse(string $query, int $inside = self::MIN_LENGTH): self
	{
		preg_match_all('/(-?)"([^"]*)"?|([^\s"]+)/u', $query, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
		$tokens = [];
		$or = false;

		foreach ($matches as $match) {
			$bare = $match[3] ?? '';

			if ($bare !== '' && strcasecmp($bare, 'or') === 0) {
				$or = $tokens !== [];

				continue;
			}

			if ($bare === '') {
				$text = trim(preg_replace('/\s+/u', ' ', $match[2] ?? '') ?? '');
				$negated = $match[1] === '-';
				$phrase = true;
			} else {
				$text = preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $bare) ?? '';
				$negated = str_starts_with($bare, '-');
				$phrase = false;
			}

			if (preg_match('/[\p{L}\p{N}]/u', $text) !== 1) {
				continue;
			}

			$tokens[] = ['text' => $text, 'negated' => $negated, 'phrase' => $phrase, 'joined' => $or];
			$or = false;
		}

		$partial = count($tokens) <= self::MAX_TERMS;
		$groups = [];

		foreach ($tokens as $token) {
			$term = new Term(
				$token['text'],
				negated: $token['negated'],
				phrase: $token['phrase'],
				inside: $partial && !$token['phrase'] && mb_strlen($token['text']) >= $inside,
			);

			if ($token['joined'] && $groups !== []) {
				$groups[count($groups) - 1][] = $term;
			} else {
				$groups[] = [$term];
			}
		}

		return new self($groups);
	}

	/**
	 * The groups a match needs: those with an alternative that is not
	 * excluded.
	 *
	 * @return list<non-empty-list<Term>>
	 */
	public function required(): array
	{
		return array_values(array_filter(
			$this->groups,
			static fn(array $group): bool => array_any($group, static fn(Term $term): bool => !$term->negated),
		));
	}
}
