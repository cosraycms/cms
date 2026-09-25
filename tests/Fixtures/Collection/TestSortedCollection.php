<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Column;
use Cosray\Contract\Columns;
use Cosray\Contract\Entries;
use Cosray\Finder\Nodes;
use Cosray\Finder\SortField;
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
			Column::new('Name', static fn(Wrapper $node): string => $node->lastName . ', ' . $node->firstName)
				->sort('name', fields: ['lastName', 'firstName'], default: true),
			Column::new('Amount', 'amount')->sort('amount', fields: [SortField::number('amount')]),
			Column::new('Start', 'start')->date(true)->sort(
				'start',
				fields: [SortField::dateTime('start')],
				direction: 'desc',
			),
			Column::new('Created', 'meta.created')->date(true)->sort('created', direction: 'desc'),
			Column::new('Editor', 'meta.editor'),
		];
	}
}
