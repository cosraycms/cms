<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;
use Cosray\Schema\Fulltext;
use Cosray\Schema\FulltextWeight;
use Cosray\Schema\Record;

#[Record]
final class FulltextRecord
{
	#[Fulltext(FulltextWeight::B)]
	protected Text $message;
}
