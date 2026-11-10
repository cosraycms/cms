<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Schema\Record;

#[Record]
class RecordNode implements Title
{
	public function title(): string
	{
		return 'record';
	}
}
