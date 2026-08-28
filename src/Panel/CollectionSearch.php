<?php

declare(strict_types=1);

namespace Cosray\Panel;

final class CollectionSearch
{
	/** @param list<array{name: string, value: string}> $fields */
	private function __construct(
		public readonly string $value,
		public readonly string $action,
		public readonly array $fields,
		public readonly ?string $clearUrl,
	) {}

	public static function from(CollectionUrls $urls): self
	{
		$query = $urls->query;

		return new self(
			value: $query->q,
			action: $urls->path(),
			fields: self::fields($query),
			clearUrl: $query->q === ''
				? null
				: $urls->collection(['q' => '', 'offset' => '']),
		);
	}

	/** @return list<array{name: string, value: string}> */
	private static function fields(CollectionQuery $query): array
	{
		$fields = [];

		if ($query->sort !== '') {
			$fields[] = ['name' => 'sort', 'value' => $query->sort];
		}

		if ($query->dir !== '') {
			$fields[] = ['name' => 'dir', 'value' => $query->dir];
		}

		if ($query->limit !== 50) {
			$fields[] = ['name' => 'limit', 'value' => (string) $query->limit];
		}

		if ($query->parent !== null) {
			$fields[] = ['name' => 'parent', 'value' => $query->parent];
		}

		if ($query->view !== $query->defaultView) {
			$fields[] = ['name' => 'view', 'value' => $query->view];
		}

		if ($query->open !== []) {
			$fields[] = ['name' => 'open', 'value' => implode(',', $query->open)];
		}

		return $fields;
	}
}
