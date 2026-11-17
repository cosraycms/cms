<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Cosray\Fulltext\Query;
use Cosray\Fulltext\Term;

/**
 * The SQL of a search: each group of the query must match in one of the
 * sources, the excluded terms in none. Sources are text expressions, the
 * uid, and the node's full-text document in the search locale.
 */
final class SearchCompiler
{
	/** The least score of a match beyond the plain full-text query, below a whole-word match in body text (`0.1`) … */
	public const SCORE = 0.05;

	/** … plus up to this much as its terms appear in the title, below a whole-word title match (`1.0`). */
	public const TITLE_SCORE = 0.45;

	/** Added as a share of the terms its type matches: a match by type alone stays below a whole-word body match. */
	public const TYPE_SCORE = 0.04;

	/** A node whose uid a term names ranks above any full-text score. */
	public const UID_SCORE = 10.0;

	/**
	 * The materialized title's values, never its locale keys, so computed titles without a content field count
	 * too. A node without a title map, such as a type without Title, has none.
	 */
	public const TITLE = "(SELECT string_agg(value, ' ') FROM jsonb_each_text(CASE WHEN jsonb_typeof(n.title) = 'object' THEN n.title ELSE '{}' END))";

	private const QUERY = 'websearch_to_tsquery(:search_config::regconfig, :fulltext)';
	private const RANK = 'websearch_to_tsquery(:search_config::regconfig, :search_rank)';
	private const FOLD = '/*:cms.prefix:*/fulltext_fold';

	// The fold maps fullwidth ％ and ＿ to % and _, so patterns are escaped after it.
	private const LIKE = <<<'SQL'
		('%' || regexp_replace({fold}({term}), '([\\%_])', '\\\1', 'g') || '%')
		SQL;

	private const WORD = <<<'SQL'
		('(^|[^[:alnum:]])' || regexp_replace(regexp_replace({fold}({term}), '([^[:alnum:][:space:]])', '\\\1', 'g'), '[[:space:]]+', '[[:space:]]+', 'g') || '([^[:alnum:]]|$)')
		SQL;

	/** @var array<string, string> */
	private array $params = [];

	/** @var array<int, array<int, string>> parameter names by group and term */
	private array $names = [];

	/**
	 * @param list<string> $texts
	 * @param list<string> $types
	 */
	private function __construct(
		private readonly Query $query,
		private readonly bool $websearch,
		private readonly bool $terms,
		private readonly array $texts,
		private readonly bool $uid,
		private readonly bool $document,
		string $prefix,
		private readonly array $types = [],
	) {
		foreach ($query->groups as $g => $group) {
			foreach ($group as $t => $term) {
				$name = "{$prefix}_{$g}_{$t}";
				$this->names[$g][$t] = $name;
				$this->params[$name] = $term->text;
			}
		}
	}

	/**
	 * The document matches the query as `websearch_to_tsquery()` reads it;
	 * with `$partial`, also when every group matches term by term, words
	 * inside longer ones included.
	 */
	public static function fulltext(string $query, bool $partial): self
	{
		return new self(Query::parse($query), true, $partial, [], false, true, 'search_term');
	}

	/**
	 * Every group matches term by term in one of `$texts`, the uid when
	 * `$uid`, the document when `$document`, or one of `$types`, the
	 * expressions naming a node's type, which also raise its score a little.
	 *
	 * @param list<string> $texts SQL expressions whose text a term may match
	 * @param list<string> $types
	 */
	public static function sources(
		Query $query,
		array $texts,
		bool $uid,
		bool $document,
		string $prefix = 'search_term',
		array $types = [],
	): self {
		return new self($query, false, true, $texts, $uid, $document, $prefix, $types);
	}

	/** Whether a node without a document can still match through another source. */
	public function outer(): bool
	{
		return $this->texts !== [] || $this->types !== [] || $this->uid;
	}

	public function match(): string
	{
		if ($this->websearch) {
			$match = 'ft.document @@ ' . self::QUERY;

			return $this->terms && $this->query->required() !== []
				? "{$match}\n\t\tOR ({$this->condition()})"
				: $match;
		}

		return $this->condition();
	}

	public function score(): string
	{
		$rank = $this->websearch ? self::QUERY : self::RANK;

		if (!$this->terms || $this->query->required() === []) {
			return "coalesce(ts_rank_cd(ft.document, {$rank}), 0)";
		}

		$scores = [
			"ts_rank_cd(ft.document, {$rank})",
			self::SCORE . ' + ' . self::TITLE_SCORE . ' * ' . $this->share(self::TITLE),
		];

		if ($this->uid) {
			$scores[] = 'CASE WHEN ' . $this->uidNamed() . ' THEN ' . self::UID_SCORE . ' ELSE 0 END';
		}

		$score = 'coalesce(GREATEST(' . implode(', ', $scores) . '), 0)';

		return $this->types === [] ? $score : "{$score} + " . self::TYPE_SCORE . ' * ' . $this->share(...$this->types);
	}

	/** The query a snippet highlights: whole words that contain a term are marked too. */
	public function headline(): string
	{
		$query = $this->websearch ? self::QUERY : self::RANK;
		$inside = [];

		foreach ($this->required() as [$term, $name]) {
			if ($term->inside && $this->terms) {
				$inside[] = 'OR ' . self::FOLD . '(word) LIKE ' . $this->pattern(self::LIKE, $name);
			}
		}

		if ($inside === []) {
			return $query;
		}

		return (
			"{$query} || coalesce((\n\t\tSELECT websearch_to_tsquery(:search_config::regconfig, string_agg(DISTINCT word, ' OR '))\n"
				. "\t\tFROM regexp_split_to_table(ft.source, '[[:space:][:punct:]]+') AS word\n"
				. "\t\tWHERE false\n\t\t\t"
				. implode("\n\t\t\t", $inside)
				. "\n\t), ''::tsquery)"
		);
	}

	/** @return array<string, string> */
	public function params(): array
	{
		if (!$this->terms) {
			return [];
		}

		if ($this->websearch || !$this->document) {
			return $this->params;
		}

		// Ranks by any required term, as the others may match outside the document.
		$rank = implode(' OR ', array_map(
			static fn(array $required): string => '"' . $required[0]->text . '"',
			$this->required(),
		));

		return [...$this->params, 'search_rank' => $rank];
	}

	/** Every group matches in one of the sources. */
	public function condition(): string
	{
		if ($this->query->groups === []) {
			return 'FALSE';
		}

		$groups = [];

		foreach ($this->query->groups as $g => $group) {
			$alternatives = [];

			foreach ($group as $t => $term) {
				$sources = $this->sourcesOf($term, $this->names[$g][$t]);
				$alternatives[] = $term->negated ? "NOT ({$sources})" : "({$sources})";
			}

			$groups[] = count($alternatives) === 1 ? $alternatives[0] : '(' . implode(' OR ', $alternatives) . ')';
		}

		return implode("\n\t\tAND ", $groups);
	}

	/** The share of required groups found in one of `$expressions`, from 0 to 1. */
	private function share(string ...$expressions): string
	{
		$found = [];

		foreach ($this->query->groups as $g => $group) {
			$alternatives = [];

			foreach ($group as $t => $term) {
				if (!$term->negated) {
					foreach ($expressions as $expression) {
						$alternatives[] = $this->text($expression, $term, $this->names[$g][$t]);
					}
				}
			}

			if ($alternatives !== []) {
				$found[] = '(' . implode(' OR ', $alternatives) . ')::int';
			}
		}

		return '((0 + ' . implode(' + ', $found) . ')::float / ' . count($found) . ')';
	}

	private function sourcesOf(Term $term, string $name): string
	{
		$sources = array_map(
			fn(string $expression): string => $this->text($expression, $term, $name),
			[...$this->texts, ...$this->types],
		);

		if ($this->uid) {
			$sources[] = $term->inside
				? "starts_with(lower(n.uid), lower(:{$name}))"
				: "lower(n.uid) = lower(:{$name})";
		}

		if ($this->document) {
			$sources[] = $this->documentMatch($term, $name);
		}

		return $sources === [] ? 'FALSE' : implode(' OR ', $sources);
	}

	private function text(string $expression, Term $term, string $name): string
	{
		$value = self::FOLD . "(coalesce(({$expression})::text, ''))";

		return $term->inside
			? "{$value} LIKE " . $this->pattern(self::LIKE, $name)
			: "{$value} ~ " . $this->pattern(self::WORD, $name);
	}

	private function documentMatch(Term $term, string $name): string
	{
		$tsquery = ($term->phrase ? 'phraseto_tsquery' : 'plainto_tsquery') . "(:search_config::regconfig, :{$name})";
		$match = "ft.document @@ {$tsquery}";

		if ($term->inside) {
			$match .= ' OR ' . self::FOLD . '(ft.source) LIKE ' . $this->pattern(self::LIKE, $name);
		}

		// Column-free guards fold to constants because PDO inlines the values and the functions are
		// immutable; otherwise they would block the indexes. A stopword takes no part in the match,
		// unless the whole query consists of them.
		if (!$term->negated) {
			$match = 'numnode(' . self::QUERY . ") > 0 AND (numnode({$tsquery}) = 0 OR {$match})";
		}

		return $this->outer() ? "coalesce({$match}, false)" : "({$match})";
	}

	/** Whether a term names the node's uid. */
	private function uidNamed(): string
	{
		$names = array_map(static fn(array $required): string => "lower(:{$required[1]})", $this->required());

		return 'lower(n.uid) IN (' . implode(', ', $names) . ')';
	}

	/**
	 * The terms that are not excluded, with their parameter names.
	 *
	 * @return list<array{0: Term, 1: string}>
	 */
	private function required(): array
	{
		$required = [];

		foreach ($this->query->groups as $g => $group) {
			foreach ($group as $t => $term) {
				if (!$term->negated) {
					$required[] = [$term, $this->names[$g][$t]];
				}
			}
		}

		return $required;
	}

	private function pattern(string $pattern, string $name): string
	{
		return strtr($pattern, ['{fold}' => self::FOLD, '{term}' => ":{$name}"]);
	}
}
