<?php

declare(strict_types=1);

namespace Cosray\Collection;

use Cosray\Column;
use Cosray\Contract\Columns;
use Cosray\Contract\Entries;
use Cosray\Finder\Nodes;
use Cosray\Schema\Label;
use Cosray\Schema\Listing;

/**
 * The built-in collection at the top of the content rail: every node of
 * every type, most recently changed first, searchable by title.
 */
#[Label('collection:all'), Listing(search: ['title'])]
final class AllContent implements Entries, Columns
{
	public function entries(Nodes $nodes): Nodes
	{
		return $nodes;
	}

	public function columns(): array
	{
		return [
			Column::new(__('collection:column-title'), 'title')->bold(true)->sort('title'),
			Column::new(__('collection:column-type'), 'meta.name'),
			Column::new(__('collection:column-editor'), 'meta.editor'),
			Column::new(__('collection:column-created'), 'meta.created')
				->date(true)
				->sort('created', direction: 'desc'),
			Column::new(__('collection:column-changed'), 'meta.changed')
				->date(true)
				->sort('changed', direction: 'desc', default: true),
		];
	}
}
