<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Node\Factory;
use Cosray\Node\Node;
use Cosray\Node\Types;
use Cosray\Plugin;
use Generator;
use Iterator;

final class Nodes implements Iterator
{
	use CompilesField;

	private string $whereFields = '';
	private string $whereTypes = '';
	private string $order = '';
	private ?int $limit = null;
	private ?int $offset = null;
	private ?bool $deleted = false; // defaults to false, if all nodes are needed set $deleted to null
	private ?bool $published = true; // ditto
	private ?bool $hidden = false; // ditto
	private array $whereParams = [];
	private readonly array $builtins;
	private Generator $result;

	public function __construct(
		private readonly Context $context,
		private readonly Cms $cms,
		private readonly Factory $nodeFactory,
		private readonly Types $types,
	) {
		$this->builtins = [
			'changed' => 'n.changed',
			'created' => 'n.created',
			'creator' => 'uc.uid',
			'editor' => 'ue.uid',
			'deleted' => 'n.deleted',
			'id' => 'n.uid',
			'locked' => 'n.locked',
			'published' => 'n.published',
			'hidden' => 'n.hidden',
			'parent' => '(SELECT p.uid FROM cms.nodes p WHERE p.node = n.parent)',
			'routable' => $this->typeFlagExpression(
				fn(string $class): bool => (bool) $this->types->get($class, 'routable', false),
			),
			'renderable' => $this->typeFlagExpression(
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

	public function search(string $query, array $fields): self
	{
		$query = trim($query);

		if ($query === '') {
			return $this;
		}

		$fields = array_values(array_filter(array_map('trim', $fields)));

		if ($fields === []) {
			return $this;
		}

		$terms = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY);

		if (!is_array($terms) || $terms === []) {
			return $this;
		}

		$expressions = array_map(
			$this->fieldExpression(...),
			$fields,
		);
		$termClauses = [];

		foreach ($terms as $term) {
			$needle = $this->context->db->quote('%' . $term . '%');
			$fieldClauses = array_map(
				static fn(string $expression): string => "COALESCE(({$expression})::text, '') ILIKE {$needle}",
				$expressions,
			);

			$termClauses[] = '(' . implode(' OR ', $fieldClauses) . ')';
		}

		$this->addWhere(implode(' AND ', $termClauses));

		return $this;
	}

	public function types(string ...$types): self
	{
		$this->whereTypes = $this->typesCondition($types);

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

		$this->addWhere(
			'n.parent = (SELECT p.node FROM cms.nodes p WHERE p.uid = :' . $param . ')',
		);
		$this->whereParams[$param] = $uid;

		return $this;
	}

	public function order(string ...$order): self
	{
		$compiler = new OrderCompiler($this->builtins, $this->context);
		$this->order = $compiler->compile(implode(',', $order));

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

	public function rewind(): void
	{
		if (!isset($this->result)) {
			$this->fetchResult();
		}
		$this->result->rewind();
	}

	public function current(): Node
	{
		if (!isset($this->result)) {
			$this->fetchResult();
		}

		$page = $this->result->current();

		$page['content'] = json_decode($page['content'], true);
		$page['editor_data'] = json_decode($page['editor_data'], true);
		$page['creator_data'] = json_decode($page['creator_data'], true);
		$page['paths'] = json_decode($page['paths'], true);
		$class = $this->context
			->container
			->tag(Plugin::NODE_TAG)
			->entry($page['type_handle'])
			->definition();

		$node = $this->nodeFactory->create($class, $this->context, $this->cms, $page);

		return $this->nodeFactory->proxy($node, $this->context->request, $this->context, $this->cms);
	}

	public function key(): int
	{
		return $this->result->key();
	}

	public function next(): void
	{
		$this->result->next();
	}

	public function valid(): bool
	{
		return $this->result->valid();
	}

	private function fetchResult(): void
	{
		$params = $this->baseParams();

		if ($this->order) {
			$params['order'] = $this->order;
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

	private function typeFlagExpression(callable $flag): string
	{
		$handles = [];
		$types = $this->context->container->tag(Plugin::NODE_TAG);

		foreach ($types->entries() as $handle) {
			$class = $types->entry($handle)->definition();

			if (!is_string($class) || !class_exists($class) || !$flag($class)) {
				continue;
			}

			$handles[] = $this->context->db->quote($handle);
		}

		sort($handles);

		if ($handles === []) {
			return 'FALSE';
		}

		return 't.handle IN (' . implode(', ', $handles) . ')';
	}
}
