<?php

declare(strict_types=1);

namespace Cosray;

use Cosray\Collection\Schema;
use Cosray\Collection\Schemas;
use Cosray\Finder\Nodes;

/**
 * Collections are configured through class attributes: #[Label],
 * #[Handle], #[Icon], #[Badge], #[Permission], #[Hidden], #[Order],
 * #[Listing], #[Blueprints]. Behavior stays on methods.
 */
abstract class Collection
{
	protected readonly Schema $schema;

	public function __construct(
		public readonly ?Cms $cms = null,
		?Schemas $schemas = null,
	) {
		$schemas ??= new Schemas();
		$this->schema = $schemas->of(static::class);
	}

	abstract public function entries(): Nodes;

	public CollectionListMeta $listMeta {
		get => $this->schema->listing;
	}

	/** @return list<class-string> */
	public function blueprints(): array
	{
		return $this->schema->blueprints;
	}

	/** @return list<Column> */
	public function columns(): array
	{
		return [
			Column::new(__('collection:column-title'), 'title')->bold(true)->sort('title'),
			Column::new(__('collection:column-changed'), 'meta.changed')->date(true)->sort(
				'changed',
				direction: 'desc',
			),
		];
	}

	public function searchFields(): array
	{
		return ['uid', 'title'];
	}
}
