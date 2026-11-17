<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Fulltext\Configurations;
use Cosray\Fulltext\Query;
use Cosray\Fulltext\Search;
use Cosray\Fulltext\Snippet;
use Cosray\Node\Factory;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
use Generator;
use Iterator;
use Override;

/** @implements Iterator<int, Wrapper> */
final class Nodes implements Iterator
{
	use CompilesField;

	private string $whereFields = '';
	private string $whereTypes = '';
	private string $order = '';
	/** @var array<string, string> */
	private array $orderParams = [];
	private ?int $limit = null;
	private ?int $offset = null;
	private ?string $fulltext = null;
	private ?SearchCompiler $search = null;
	private ?bool $activeUrl = null;
	private readonly Configurations $configurations;
	private ?bool $deleted = false; // defaults to false, if all nodes are needed set $deleted to null
	private ?bool $published = true; // ditto
	private ?bool $hidden = false; // ditto
	private ?int $mine = null;
	/** @var array<string, string> */
	private array $whereParams = [];
	/** @var array<string, string> */
	private readonly array $builtins;
	private Generator $result;

	public function __construct(
		private readonly Context $context,
		private readonly Cms $cms,
		private readonly Factory $nodeFactory,
		private readonly Types $types,
	) {
		$this->configurations = new Configurations($context->db);
		$this->builtins = [
			'changed' => 'n.changed',
			'changes' => 'd.node IS NOT NULL',
			'created' => 'n.created',
			'creator' => 'uc.uid',
			'editor' => 'ue.uid',
			'deleted' => 'n.deleted',
			'id' => 'n.uid',
			'locked' => 'n.locked',
			// A node without a publication row, a record, is neither.
			'published' => 'coalesce(p.published, false)',
			'hidden' => 'coalesce(p.hidden, false)',
			'parent' => 'np.uid',
			'routable' => $this->typeFlagExpression(
				/** @param class-string $class */
				fn(string $class): bool => (bool) $this->types->get($class, 'routable', false),
			),
			'renderable' => $this->typeFlagExpression(
				/** @param class-string $class */
				fn(string $class): bool => (bool) $this->types->get($class, 'renderable', false),
			),
			'type' => 't.handle',
			'handle' => 'h.handle',
			'uid' => 'n.uid',
		];
	}

	public function filter(string $query): self
	{
		$compiler = new QueryCompiler($this->context, $this->builtins);
		$this->addWhere($compiler->compile($query));

		return $this;
	}

	/**
	 * A structured filter, see Filter; `$builtins` limits the builtins it
	 * may use, null allows all.
	 *
	 * @param array<array-key, mixed> $filter
	 * @param ?list<string> $builtins
	 */
	public function match(array $filter, ?array $builtins = null): self
	{
		$this->addWhere(new Filter($this->context, $this->builtins, $builtins)->compile($filter));

		return $this;
	}

	/**
	 * Only what a reader may see: nodes of the `$any` types in every state,
	 * nodes of the `$live` types when published and not deleted. With
	 * `$removals`, the `$live` types' nodes come in every state as long as
	 * they have publication, so a sync can report those that went away.
	 *
	 * @param list<string> $live type handles
	 * @param list<string> $any type handles
	 */
	public function readable(array $live, array $any, bool $removals = false): self
	{
		$in = fn(array $handles): string => (
			't.handle IN ('
			. implode(', ', array_map(fn(string $handle): string => $this->context->db->quote($handle), $handles))
			. ')'
		);
		$conditions = [];

		if ($any !== []) {
			$conditions[] = $in($any);
		}

		if ($live !== []) {
			$conditions[] =
				'(' . $in($live) . ($removals ? ' AND p.node IS NOT NULL)' : ' AND p.published AND n.deleted IS NULL)');
		}

		$this->addWhere($conditions === [] ? 'FALSE' : '(' . implode(' OR ', $conditions) . ')');

		return $this;
	}

	/** With `$partial`, a word also matches inside longer words, such as `ball` in `football`. */
	public function fulltext(string $query, bool $partial = false): self
	{
		$this->fulltext = $query;
		$this->search = SearchCompiler::fulltext($query, $partial);

		return $this;
	}

	public function activeUrl(bool $required = true): self
	{
		$this->activeUrl = $required;

		return $this;
	}

	/**
	 * A search on `$fields`: each word or phrase of the query occurs in one
	 * of them, words of three characters and more also inside longer words.
	 * `uid` matches a whole uid or its start, `type` a type's handle or its
	 * label as the panel shows it. With `$fulltext`, a word may also match
	 * through the node's full-text document in the current locale, and
	 * results order by relevance unless order() is set; unlike fulltext(),
	 * it does not require an active URL unless activeUrl() asks for one.
	 *
	 * @param array<string> $fields
	 */
	public function search(string $query, array $fields, bool $fulltext = false): self
	{
		return $this->sources(Query::parse($query), $query, $fields, $fulltext);
	}

	/** A title search as a picker runs it while the editor types: words match inside longer ones from their first letter. */
	public function searchTitle(string $query): self
	{
		return $this->sources(Query::parse($query, inside: 1), $query, ['title'], false);
	}

	/** @param array<string> $fields */
	private function sources(Query $parsed, string $query, array $fields, bool $fulltext): self
	{
		$query = trim($query);
		$fields = array_values(array_filter(
			array_map('trim', $fields),
			static fn(string $field): bool => $field !== '',
		));

		if ($query === '' || $fields === [] && !$fulltext) {
			return $this;
		}

		$texts = [];
		$uid = false;

		foreach ($fields as $field) {
			match ($field) {
				'uid', 'id' => $uid = true,
				'title' => $texts[] = SearchCompiler::TITLE,
				'type' => array_push($texts, 't.handle', $this->typeLabels()),
				default => $texts[] = $this->fieldExpression($field),
			};
		}

		if (!$fulltext) {
			$search = SearchCompiler::sources($parsed, $texts, $uid, false, 'search_' . count($this->whereParams));
			$this->addWhere($search->condition());
			$this->whereParams = [...$this->whereParams, ...$search->params()];

			return $this;
		}

		$this->fulltext = $query;
		$this->search = SearchCompiler::sources($parsed, $texts, $uid, true);
		$this->activeUrl ??= false;

		return $this;
	}

	public function types(string ...$types): self
	{
		$this->whereTypes = $this->typesCondition(array_values($types));

		return $this;
	}

	public function type(string $type): self
	{
		$this->whereTypes = $this->typesCondition([$type]);

		return $this;
	}

	public function roots(): self
	{
		$this->addWhere('n.parent IS NULL');

		return $this;
	}

	public function childrenOf(string $uid): self
	{
		$uid = trim($uid);

		if ($uid === '') {
			throw new RuntimeException('Parent uid is required');
		}

		$param = 'parent_uid_' . count($this->whereParams);

		$this->addWhere('np.uid = :' . $param);
		$this->whereParams[$param] = $uid;

		return $this;
	}

	/** Restrict the result to the given node uids (order-independent). */
	public function only(string ...$uids): self
	{
		$uids = array_values(array_filter(
			array_map('trim', $uids),
			static fn(string $uid): bool => $uid !== '',
		));

		if ($uids === []) {
			$this->addWhere('FALSE');

			return $this;
		}

		$placeholders = [];

		foreach ($uids as $uid) {
			$param = 'only_uid_' . count($this->whereParams);
			$this->whereParams[$param] = $uid;
			$placeholders[] = ':' . $param;
		}

		$this->addWhere('n.uid IN (' . implode(', ', $placeholders) . ')');

		return $this;
	}

	/** Exclude specific node uids from the result (e.g. self-reference). */
	public function exclude(string ...$uids): self
	{
		foreach ($uids as $uid) {
			$uid = trim($uid);

			if ($uid === '') {
				continue;
			}

			$param = 'exclude_uid_' . count($this->whereParams);
			$this->addWhere('n.uid <> :' . $param);
			$this->whereParams[$param] = $uid;
		}

		return $this;
	}

	public function order(string|Order ...$order): self
	{
		$compiler = new OrderCompiler(
			$this->builtins
				+ ['title' => fn(): string => $this->context->titleSort()->order($this->context->locale(), 'n.title')],
			$this->context,
		);
		$this->order = $compiler->compile(...$order);
		$this->orderParams = $compiler->params();

		return $this;
	}

	public function limit(int $limit): self
	{
		$this->limit = $limit;

		return $this;
	}

	public function offset(int $offset): self
	{
		$this->offset = $offset;

		return $this;
	}

	public function count(): int
	{
		$record = $this->context
			->db
			->nodes
			->count($this->baseParams())
			->one();

		return (int) ($record['count'] ?? 0);
	}

	/**
	 * How many nodes each type holds, by type handle, under the same
	 * conditions as count(); types without a node are left out.
	 *
	 * @return array<string, int>
	 */
	public function countByType(): array
	{
		$counts = [];

		foreach ($this->context->db->nodes->count([...$this->baseParams(), 'by_type' => true])->all() as $record) {
			$counts[(string) $record['type']] = (int) $record['count'];
		}

		return $counts;
	}

	public function published(?bool $published): self
	{
		$this->published = $published;

		return $this;
	}

	public function hidden(?bool $hidden): self
	{
		$this->hidden = $hidden;

		return $this;
	}

	public function deleted(?bool $deleted): self
	{
		$this->deleted = $deleted;

		return $this;
	}

	/** Loads a user's autosave facts with every node, as `meta->mine`. */
	public function mine(int $user): self
	{
		$this->mine = $user;

		return $this;
	}

	/** Only nodes with publication: a record has none. */
	public function withPublication(): self
	{
		$this->addWhere('p.node IS NOT NULL');

		return $this;
	}

	/** Only nodes of types that keep working copies. */
	public function withDrafts(): self
	{
		$this->addWhere($this->typeFlagExpression(
			/** @param class-string $class */
			fn(string $class): bool => (bool) $this->types->get($class, 'drafts', true),
		));

		return $this;
	}

	/**
	 * Narrows to the nodes in one stage of the panel's status: `draft` for a
	 * working copy, `mine` for the user's own unsaved edits, and
	 * `unpublished` for a node with publication that visitors do not see.
	 */
	public function stage(string $stage, int $user): self
	{
		$this->addWhere(match ($stage) {
			'draft' => 'd.node IS NOT NULL AND '
				. $this->typeFlagExpression(
					/** @param class-string $class */
					fn(string $class): bool => (bool) $this->types->get($class, 'drafts', true),
				),
			'mine' => "EXISTS (SELECT 1 FROM /*:cms.prefix:*/edits m WHERE m.node = n.node AND m.editor = {$user})",
			'unpublished' => 'p.published = false',
			default => throw new RuntimeException("Unknown status stage '{$stage}'"),
		});

		return $this;
	}

	#[Override]
	public function rewind(): void
	{
		if (!isset($this->result)) {
			$this->fetchResult();
		}
		$this->result->rewind();
	}

	#[Override]
	public function current(): Wrapper
	{
		if (!isset($this->result)) {
			$this->fetchResult();
		}

		$row = $this->result->current();

		$row['content'] = json_decode($row['content'], true);
		$row['title'] = json_decode($row['title'], true);
		$row['editor_data'] = json_decode((string) $row['editor_data'], true);
		$row['creator_data'] = json_decode($row['creator_data'], true);
		$row['paths'] = json_decode($row['paths'], true);
		$row = Node::foldDraft($row);
		if (isset($row['search_score'], $row['search_headline'])) {
			$row['search'] = new Search((float) $row['search_score'], new Snippet($row['search_headline']));
			unset($row['search_score'], $row['search_headline']);
		}
		$class = $this->context
			->container
			->tag(Bootstrap::NODE_TAG)
			->entry($row['type_handle'])
			->definition();

		$node = $this->nodeFactory->create($class, $this->context, $this->cms, $row);

		return $this->nodeFactory->proxy($node, $this->context, $this->cms);
	}

	#[Override]
	public function key(): int
	{
		return $this->result->key();
	}

	#[Override]
	public function next(): void
	{
		$this->result->next();
	}

	#[Override]
	public function valid(): bool
	{
		return $this->result->valid();
	}

	private function fetchResult(): void
	{
		$params = $this->baseParams();

		if ($this->order) {
			$params['order'] = $this->order;
			$params = [...$params, ...$this->orderParams];
		} elseif ($this->search?->outer()) {
			// Nodes found only through the searched fields score alike, so the title orders them.
			$title = $this->context->titleSort()->order($this->context->locale(), 'n.title');
			$params['order'] = "search_score DESC, {$title} ASC, n.uid ASC";
		} elseif ($this->search !== null) {
			$params['order'] = 'search_score DESC, n.uid ASC';
		}

		if ($this->limit !== null) {
			$params['limit'] = $this->limit;
		}

		if ($this->offset !== null) {
			$params['offset'] = $this->offset;
		}

		$this->result = $this->context
			->db
			->nodes
			->find($params)
			->lazy();
	}

	/** @return array<string, mixed> */
	private function baseParams(): array
	{
		$conditions = implode(' AND ', array_filter(
			[
				trim($this->whereFields),
				trim($this->whereTypes),
			],
			static fn(string $clause): bool => $clause !== '',
		));

		$params = [
			'condition' => $conditions,
		];

		if ($this->whereParams !== []) {
			$params = array_merge($params, $this->whereParams);
		}

		if (is_bool($this->deleted)) {
			$params['deleted'] = $this->deleted;
		}

		if (is_bool($this->published)) {
			$params['published'] = $this->published;
		}

		if (is_bool($this->hidden)) {
			$params['hidden'] = $this->hidden;
		}

		if ($this->mine !== null) {
			$params['mine'] = $this->mine;
		}

		if ($this->search !== null) {
			$params = [
				...$params,
				...$this->search->params(),
				'fulltext' => $this->fulltext,
				'search_outer' => $this->search->outer(),
				'search_match' => $this->search->match(),
				'search_score' => $this->search->score(),
				'search_headline' => $this->search->headline(),
				'search_locale' => $this->context->localeId(),
				'search_config' => $this->configurations->for($this->context->locale()),
			];
		}

		// An explicit activeUrl() wins; otherwise the gate is on while fulltext() is active.
		if ($this->activeUrl ?? $this->search !== null) {
			$params['active_url'] = true;
			$params['url_locales'] = $this->localeIds();
			$params['routable'] = $this->builtins['routable'];
		}

		return $params;
	}

	private function addWhere(string $clause): void
	{
		$clause = trim($clause);

		if ($clause === '') {
			return;
		}

		if ($this->whereFields === '') {
			$this->whereFields = $clause;

			return;
		}

		$this->whereFields = "({$this->whereFields}) AND ({$clause})";
	}

	private function fieldExpression(string $field): string
	{
		$builtin = $this->builtins[$field] ?? null;

		if (is_string($builtin) && $builtin !== '') {
			return $builtin;
		}

		if (!preg_match('/^[A-Za-z][A-Za-z0-9._-]*$/', $field)) {
			throw new RuntimeException('Invalid field name for search: ' . $field);
		}

		return $this->compileField($field, 'n.content', localeIds: $this->localeIds());
	}

	/** @return list<string> */
	private function localeIds(): array
	{
		$ids = [];
		$locale = $this->context->locale();

		while ($locale) {
			$ids[] = $locale->id;
			$locale = $locale->fallback();
		}

		return $ids;
	}

	/** Each registered type's label as the panel shows it, translated. */
	private function typeLabels(): string
	{
		$labels = [];
		$types = $this->context->container->tag(Bootstrap::NODE_TAG);

		foreach ($types->entries() as $handle) {
			$class = $types->entry($handle)->definition();

			if (is_string($class) && class_exists($class)) {
				$labels[] =
					'WHEN '
					. $this->context->db->quote($handle)
					. ' THEN '
					. $this->context->db->quote(
						__((string) $this->types->get($class, 'label')),
					);
			}
		}

		return $labels === [] ? "''" : 'CASE t.handle ' . implode(' ', $labels) . ' END';
	}

	/** @param list<string> $types */
	private function typesCondition(array $types): string
	{
		$result = [];

		foreach ($types as $type) {
			if (class_exists($type)) {
				$type = (string) $this->types->get($type, 'handle');
			}

			$result[] = 't.handle = ' . $this->context->db->quote($type);
		}

		return match (count($result)) {
			0 => '',
			1 => '    ' . $result[0],
			default => "    (\n        " . implode("\n        OR ", $result) . "\n    )",
		};
	}

	/** @param callable(class-string): bool $flag */
	private function typeFlagExpression(callable $flag): string
	{
		return self::flagged($this->context, $flag);
	}

	/**
	 * A condition on `t.handle` that holds for the registered types the
	 * flag holds for.
	 *
	 * @param callable(class-string): bool $flag
	 */
	public static function flagged(Context $context, callable $flag): string
	{
		$handles = [];
		$types = $context->container->tag(Bootstrap::NODE_TAG);

		foreach ($types->entries() as $handle) {
			$class = $types->entry($handle)->definition();

			if (!is_string($class) || !class_exists($class) || !$flag($class)) {
				continue;
			}

			$handles[] = $context->db->quote($handle);
		}

		sort($handles);

		if ($handles === []) {
			return 'FALSE';
		}

		return 't.handle IN (' . implode(', ', $handles) . ')';
	}
}
