<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;
use Cosray\Schema\FieldOrder;

#[FieldOrder('heading', 'missing')]
final class NodeWithUnknownFieldOrder
{
	private Text $heading;
}
