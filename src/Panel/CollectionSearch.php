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
		// No sort: a new search ranks its hits by relevance until a column is chosen.
		$fields = [];

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

		// A search narrows the filters' selection rather than replacing it.
		if ($query->stage !== '') {
			$fields[] = ['name' => 'stage', 'value' => $query->stage];
		}

		if ($query->type !== '') {
			$fields[] = ['name' => 'type', 'value' => $query->type];
		}

		return $fields;
	}
}
