<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Schema\Record;
use Cosray\Schema\Route;

#[Record, Route('/routed-record/{uid}')]
class RoutedRecord implements Title
{
	public function title(): string
	{
		return 'record';
	}
}
