<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;
use Cosray\Schema\Fulltext;
use Cosray\Schema\FulltextWeight;

final class FulltextDocument
{
	#[Fulltext(FulltextWeight::B)]
	private Text $body;
}
