<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;
use Cosray\Schema\Record;

#[Record]
class TextSubmission
{
	protected Text $message;
}
