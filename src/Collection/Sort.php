<?php

declare(strict_types=1);

namespace Cosray\Collection;

use Cosray\Exception\RuntimeException;
use Cosray\Finder\Order;
use Cosray\Finder\SortField;

final readonly class Sort
{
	/** @var non-empty-list<SortField> */
	public array $fields;
	public string $direction;

	/**
	 * @param non-empty-list<string|SortField>|null $fields
	 * @param bool $default Whether the listing starts in this order
	 */
	public function __construct(
		public string $key,
		?array $fields = null,
		string $direction = 'asc',
		public bool $default = false,
	) {
		if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/D', $key) !== 1) {
			throw new RuntimeException("Invalid collection sort key '{$key}'");
		}
		$fields ??= [$key];
		if ($fields === [] || !array_is_list($fields)) {
			throw new RuntimeException("Sort '{$key}' needs a non-empty list of fields");
		}
		$resolved = [];
		foreach ($fields as $field) {
			if (!is_string($field) && !$field instanceof SortField) {
				throw new RuntimeException("Unsupported field in sort '{$key}'");
			}
			$resolved[] = is_string($field) ? SortField::text($field) : $field;
		}
		$this->fields = $resolved;
		$this->direction = new Order($resolved[0], $direction)->direction;
	}

	/** @return non-empty-list<Order> */
	public function order(string $direction = ''): array
	{
		$direction = $direction === '' ? $this->direction : $direction;
		$order = [];
		$unique = false;
		foreach ($this->fields as $field) {
			$order[] = new Order($field, $direction);
			$unique = $unique || in_array($field->name, ['uid', 'id'], true);
		}
		if (!$unique) {
			$order[] = new Order('uid');
		}
		return $order;
	}
}
