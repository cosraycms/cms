<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Column;
use Cosray\Contract\Columns;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Listing;
use Cosray\Schema\Types;
use Cosray\Stage;

#[Label('Status columns'), Handle('test-status-columns'), Types('test-article'), Listing(hidden: true)]
final class TestStatusColumnsCollection implements Columns
{
	public function columns(): array
	{
		return [
			Column::status(Stage::Main)->title(''),
			Column::new('Title', 'title')->bold(true)->sort('title'),
			Column::status(Stage::Draft, Stage::Mine)->title('Changes')->present()->dots(),
		];
	}
}
