<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Column;
use Cosray\Contract\Columns;
use Cosray\Contract\Entries;
use Cosray\Finder\Nodes;
use Cosray\Node\Wrapper;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Types;

#[Label('Sorted entries'), Handle('test-sorted'), Types('test-sortable-entry')]
final class TestSortedCollection implements Columns, Entries
{
	public int $columnsCalls = 0;

	public function entries(Nodes $nodes): Nodes
	{
		// Intentionally different: the panel must use its declared sort.
		return $nodes->order('uid DESC');
	}

	public function columns(): array
	{
		$this->columnsCalls++;
		return [
			Column::text('Name', static fn(Wrapper $node): string => $node->lastName . ', ' . $node->firstName)
				->sort('name', fields: ['lastName', 'firstName'], default: true),
			Column::number('Amount', 'amount')->sort('amount'),
			Column::dateTime('Start', 'start')->sort('start', direction: 'desc'),
			Column::created('Created'),
			Column::editor(),
		];
	}
}
