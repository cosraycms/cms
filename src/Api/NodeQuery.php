<?php

declare(strict_types=1);

namespace Cosray\Api;

use Celema\Core\Exception\HttpBadRequest;
use Cosray\Finder\Order;
use Cosray\Finder\SortField;

/**
 * A node listing's parameters, read from a query string or from the JSON
 * body of a query request. Both take the same names; lists are
 * comma-separated in a query string and arrays in a body.
 */
final readonly class NodeQuery
{
	public const int DEFAULT_LIMIT = 50;
	public const int MAX_LIMIT = 1000;

	/** Builtins a client may filter and order on; the others name users or internals. */
	public const array BUILTINS = [
		'uid',
		'handle',
		'type',
		'parent',
		'published',
		'hidden',
		'created',
		'changed',
		'routable',
		'renderable',
	];

	/** Finder builtins that name users or internals, which clients neither filter nor order on. */
	private const array PRIVATE = ['creator', 'editor', 'changes', 'id', 'locked', 'deleted'];

	private const string NAME = '/^[A-Za-z][A-Za-z0-9_-]*(\.[A-Za-z0-9_-]+)*$/';
	private const array SORT_TYPES = ['text', 'number', 'date', 'datetime'];

	/**
	 * @param list<string> $types
	 * @param list<string> $uids
	 * @param list<string> $handles
	 * @param ?array<array-key, mixed> $filter
	 * @param non-empty-list<Order> $order
	 * @param ?list<string> $fields
	 */
	public function __construct(
		public array $types = [],
		public array $uids = [],
		public array $handles = [],
		public ?string $parent = null,
		public ?bool $published = true,
		public ?bool $hidden = false,
		public ?bool $deleted = false,
		public ?array $filter = null,
		public array $order = [new Order('title'), new Order('uid')],
		public int $limit = self::DEFAULT_LIMIT,
		public int $offset = 0,
		public ?array $fields = null,
		public bool $source = false,
	) {}

	/** @param array<array-key, mixed> $params */
	public static function fromQuery(array $params): self
	{
		$filter = $params['filter'] ?? null;

		if ($filter !== null) {
			if (!is_string($filter)) {
				throw self::invalid('`filter` is a JSON object.');
			}

			$filter = json_decode($filter, true);

			if (!is_array($filter)) {
				throw self::invalid('`filter` is a JSON object.');
			}
		}

		return self::from($params, $filter);
	}

	/** @param array<array-key, mixed> $body */
	public static function fromBody(array $body): self
	{
		$filter = $body['filter'] ?? null;

		if ($filter !== null && !is_array($filter)) {
			throw self::invalid('`filter` is an object.');
		}

		return self::from($body, $filter);
	}

	/** Whether the request asks for the source view. */
	public static function source(mixed $view): bool
	{
		return match ($view) {
			null, '', 'resolved' => false,
			'source' => true,
			default => throw self::invalid('`view` is `resolved` or `source`.'),
		};
	}

	/** @return ?list<string> */
	public static function fields(mixed $fields): ?array
	{
		return $fields === null ? null : self::names($fields, 'fields');
	}

	/** A tristate filter: true, false, or `any` for both. */
	public static function state(mixed $value, string $name, ?bool $default): ?bool
	{
		return match ($value) {
			null => $default,
			true, 'true', '1' => true,
			false, 'false', '0' => false,
			'any' => null,
			default => throw self::invalid("`{$name}` is true, false or any."),
		};
	}

	/**
	 * @param array<array-key, mixed> $params
	 * @param ?array<array-key, mixed> $filter
	 */
	private static function from(array $params, ?array $filter): self
	{
		$uids = self::list($params['uid'] ?? null, 'uid');

		if (count($uids) > self::MAX_LIMIT) {
			throw self::invalid('A query takes at most ' . self::MAX_LIMIT . ' uids.');
		}

		$parent = $params['parent'] ?? null;

		if ($parent !== null && (!is_string($parent) || trim($parent) === '')) {
			throw self::invalid('`parent` is a node uid.');
		}

		return new self(
			types: self::list($params['type'] ?? null, 'type'),
			uids: $uids,
			handles: self::list($params['handle'] ?? null, 'handle'),
			parent: $parent === null ? null : trim($parent),
			published: self::state($params['published'] ?? null, 'published', true),
			hidden: self::state($params['hidden'] ?? null, 'hidden', false),
			deleted: self::state($params['deleted'] ?? null, 'deleted', false),
			filter: $filter,
			order: self::order($params['order'] ?? null),
			limit: self::int(
				$params['limit'] ?? null,
				'limit',
				$uids === [] ? self::DEFAULT_LIMIT : count($uids),
				1,
				self::MAX_LIMIT,
			),
			offset: self::int($params['offset'] ?? null, 'offset', 0, 0, PHP_INT_MAX),
			fields: self::fields($params['fields'] ?? null),
			source: self::source($params['view'] ?? null),
		);
	}

	/**
	 * Terms like `-changed`, `title` or `-start:date`; a leading minus sorts
	 * descending. The node uid always breaks ties.
	 *
	 * @return non-empty-list<Order>
	 */
	private static function order(mixed $value): array
	{
		if ($value === null) {
			return [new Order('title'), new Order('uid')];
		}

		$orders = [];

		foreach (self::list($value, 'order') as $term) {
			$direction = str_starts_with($term, '-') ? 'desc' : 'asc';
			[$name, $type] = array_pad(explode(':', ltrim($term, '-'), 2), 2, 'text');

			if (
				preg_match(self::NAME, $name) !== 1
				|| !in_array($type, self::SORT_TYPES, true)
				|| in_array($name, self::PRIVATE, true)
			) {
				throw self::invalid("`{$term}` is not an order term.");
			}

			$orders[] = new Order(match (true) {
				$name === 'position' => SortField::position(),
				$type === 'number' => SortField::number($name),
				$type === 'date' => SortField::date($name),
				$type === 'datetime' => SortField::dateTime($name),
				default => SortField::text($name),
			}, $direction);
		}

		$orders[] = new Order('uid');

		return $orders;
	}

	/** @return list<string> */
	private static function list(mixed $value, string $name): array
	{
		if ($value === null) {
			return [];
		}

		if (is_string($value)) {
			$value = explode(',', $value);
		}

		if (!is_array($value) || !array_is_list($value)) {
			throw self::invalid("`{$name}` is a list.");
		}

		$items = [];

		foreach ($value as $item) {
			if (!is_string($item)) {
				throw self::invalid("`{$name}` holds strings.");
			}

			$item = trim($item);

			if ($item !== '') {
				$items[] = $item;
			}
		}

		return array_values(array_unique($items));
	}

	/** @return list<string> */
	private static function names(mixed $value, string $name): array
	{
		$names = self::list($value, $name);

		foreach ($names as $item) {
			if (preg_match(self::NAME, $item) !== 1) {
				throw self::invalid("`{$item}` is not a field name.");
			}
		}

		return $names;
	}

	private static function int(mixed $value, string $name, int $default, int $min, int $max): int
	{
		if ($value === null) {
			return $default;
		}

		$int = is_int($value) ? $value : filter_var($value, FILTER_VALIDATE_INT);

		if (!is_int($int) || $int < $min || $int > $max) {
			throw self::invalid("`{$name}` is a whole number from {$min} to {$max}.");
		}

		return $int;
	}

	private static function invalid(string $message): HttpBadRequest
	{
		return new HttpBadRequest(payload: ['message' => $message]);
	}
}
