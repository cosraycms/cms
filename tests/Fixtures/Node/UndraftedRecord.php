<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Schema\Drafts;
use Cosray\Schema\Record;

#[Record, Drafts(false)]
class UndraftedRecord implements Title
{
	public function title(): string
	{
		return 'undrafted record';
	}
}
