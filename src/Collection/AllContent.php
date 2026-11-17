<?php

declare(strict_types=1);

namespace Cosray\Collection;

use Cosray\Column;
use Cosray\Contract\Columns;
use Cosray\Contract\Entries;
use Cosray\Finder\Nodes;
use Cosray\Schema\Label;
use Override;

/**
 * The built-in collection at the top of the content rail: every node of
 * every type, most recently changed first.
 */
#[Label('collection:all')]
final class AllContent implements Entries, Columns
{
	#[Override]
	public function entries(Nodes $nodes): Nodes
	{
		return $nodes;
	}

	#[Override]
	public function columns(): array
	{
		return [
			Column::text(__('collection:column-title'), 'title')->bold()->sort('title'),
			Column::type(),
			Column::editor(),
			Column::created(),
			Column::changed()->sort('changed', direction: 'desc', default: true),
		];
	}
}
