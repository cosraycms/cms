<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Core\Factory\Factory;
use Celema\Core\Response;
use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Field\Reference as ReferenceField;
use Cosray\Node\Wrapper;
use Cosray\Schema\Pick;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * JSON node search backing the reference picker. The pickable set is
 * derived server-side from the reference field's own schema (never from
 * the client): non-deleted, optionally type/filter constrained, any
 * publication state, hidden included, current node excluded. Rows whose
 * node type is no longer registered are omitted because they cannot be hydrated.
 */
final class Reference extends Panel
{
	private const int LIMIT_DEFAULT = 30;
	private const int LIMIT_MAX = 100;

	public function search(Cms $cms, Factory $factory): Response
	{
		$constraints = $this->constraints($this->stringParam('type'), $this->stringParam('field'));

		if ($constraints === null) {
			return $this->result($factory, [], false);
		}

		$q = $this->stringParam('q');
		$exclude = $this->stringParam('node');
		$offset = $this->intParam('offset', 0, min: 0);
		$limit = $this->intParam('limit', self::LIMIT_DEFAULT, min: 1, max: self::LIMIT_MAX);

		$types = $this->registeredTypes($constraints['types']);

		if ($types === []) {
			return $this->result($factory, [], false);
		}

		$finder = $cms
			->nodes($constraints['where'])
			->deleted(false)
			->published($constraints['published'])
			->hidden($constraints['hidden'])
			->types(...$types)
			->order('changed DESC');

		if ($exclude !== '') {
			$finder->exclude($exclude);
		}

		if ($q !== '') {
			$finder->searchTitle($q);
		}

		$finder->offset($offset)->limit($limit + 1);

		$rows = iterator_to_array($finder, false);
		$more = count($rows) > $limit;

		return $this->result(
			$factory,
			array_map($this->item(...), array_slice($rows, 0, $limit)),
			$more,
		);
	}

	/**
	 * Unconstrained node search backing the richtext link picker. A prose
	 * link is not bound to a schema property, so the pickable set is simply
	 * every non-deleted node (any registered type, any publication), the current node
	 * excluded. Kept separate from search() so that method's reflected
	 * #[Pick] contract (constraints from the field, never the client) stays
	 * intact.
	 */
	public function nodes(Cms $cms, Factory $factory): Response
	{
		$q = $this->stringParam('q');
		$exclude = $this->stringParam('node');
		$offset = $this->intParam('offset', 0, min: 0);
		$limit = $this->intParam('limit', self::LIMIT_DEFAULT, min: 1, max: self::LIMIT_MAX);

		$types = $this->registeredTypes();

		if ($types === []) {
			return $this->result($factory, [], false);
		}

		$finder = $cms
			->nodes()
			->deleted(false)
			->published(null)
			->hidden(null)
			->types(...$types)
			->order('changed DESC');

		if ($exclude !== '') {
			$finder->exclude($exclude);
		}

		if ($q !== '') {
			$finder->searchTitle($q);
		}

		$finder->offset($offset)->limit($limit + 1);

		$rows = iterator_to_array($finder, false);
		$more = count($rows) > $limit;

		return $this->result(
			$factory,
			array_map($this->item(...), array_slice($rows, 0, $limit)),
			$more,
		);
	}

	/**
	 * Resolve titles for already-chosen uids so the control can render its
	 * selected rows. Chosen values render regardless of the pickable set;
	 * soft-deleted targets and unregistered node types drop out, and the
	 * caller's order is kept.
	 */
	public function labels(Cms $cms, Factory $factory): Response
	{
		$uids = array_values(array_filter(
			array_map('trim', explode(',', $this->stringParam('uids'))),
			static fn(string $uid): bool => $uid !== '',
		));

		if ($uids === []) {
			return $this->result($factory, [], false);
		}

		$types = $this->registeredTypes();

		if ($types === []) {
			return $this->result($factory, [], false);
		}

		$byUid = [];

		foreach ($cms
			->nodes()
			->deleted(false)
			->published(null)
			->hidden(null)
			->types(...$types)
			->only(...$uids) as $node) {
			$byUid[$node->meta->uid] = $this->item($node);
		}

		$ordered = [];

		foreach ($uids as $uid) {
			if (!isset($byUid[$uid])) {
				continue;
			}

			$ordered[] = $byUid[$uid];
		}

		return $this->result($factory, $ordered, false);
	}

	private function item(Wrapper $node): array
	{
		return [
			'uid' => $node->meta->uid,
			'title' => $node->label(),
			'type' => (string) $node->meta->type->get('handle', ''),
			'typeLabel' => (string) $node->meta->type->get('label', ''),
		];
	}

	/**
	 * Read the reference field's declared pickable-set constraints from its
	 * #[Pick] attribute. Null when the type/field is unknown or the field is
	 * not a Reference — the caller returns an empty result. A Reference field
	 * without #[Pick] yields the open defaults (any type, any publication).
	 *
	 * @return array{types: list<string>, where: string, published: ?bool, hidden: ?bool}|null
	 */
	private function constraints(string $typeHandle, string $field): ?array
	{
		$class = $this->nodeClass($typeHandle);

		if ($class === null || $field === '' || !property_exists($class, $field)) {
			return null;
		}

		$property = new ReflectionProperty($class, $field);
		$propType = $property->getType();

		if (
			!$propType instanceof ReflectionNamedType
			|| !is_a($propType->getName(), ReferenceField::class, true)
		) {
			return null;
		}

		$attributes = $property->getAttributes(Pick::class);

		if ($attributes === []) {
			return ['types' => [], 'where' => '', 'published' => null, 'hidden' => null];
		}

		$pick = $attributes[0]->newInstance();

		return [
			'types' => $pick->types,
			'where' => $pick->where,
			'published' => $pick->published,
			'hidden' => $pick->hidden,
		];
	}

	/**
	 * Limit picker queries to registered node types. Database rows can outlive
	 * a removed type, but the finder cannot hydrate them without its class.
	 *
	 * @param list<string> $requested Class names or handles from #[Pick].
	 * @return list<string>
	 */
	private function registeredTypes(array $requested = []): array
	{
		$tag = $this->container->tag(Bootstrap::NODE_TAG);
		$handles = [];

		foreach ($tag->entries() as $handle) {
			$class = $tag->entry($handle)->definition();

			if (!is_string($class) || !class_exists($class)) {
				continue;
			}

			if (
				$requested !== []
				&& !in_array($class, $requested, true)
				&& !in_array($handle, $requested, true)
			) {
				continue;
			}

			$handles[] = $handle;
		}

		return $handles;
	}

	/** @return class-string|null */
	private function nodeClass(string $handle): ?string
	{
		if ($handle === '') {
			return null;
		}

		$tag = $this->container->tag(Bootstrap::NODE_TAG);

		if (!in_array($handle, $tag->entries(), true)) {
			return null;
		}

		$class = $tag->entry($handle)->definition();

		return is_string($class) && class_exists($class) ? $class : null;
	}

	private function result(Factory $factory, array $nodes, bool $more): Response
	{
		return Response::create($factory)->json([
			'ok' => true,
			'nodes' => $nodes,
			'more' => $more,
		]);
	}

	private function stringParam(string $key): string
	{
		$value = $this->request->param($key, '');

		return is_string($value) ? trim($value) : '';
	}

	private function intParam(string $key, int $default, int $min, ?int $max = null): int
	{
		$value = $this->request->param($key, (string) $default);

		if (is_int($value)) {
			$int = $value;
		} elseif (is_string($value) && preg_match('/^-?[0-9]+$/', $value)) {
			$int = (int) $value;
		} else {
			$int = $default;
		}

		$int = max($min, $int);

		return $max === null ? $int : min($max, $int);
	}
}
