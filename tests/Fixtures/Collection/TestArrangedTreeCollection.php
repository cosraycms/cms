<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Listing;
use Cosray\Schema\Types;

/** A hierarchy with an arranged top level; children follow their parents. */
#[
	Label('Test arranged tree'),
	Handle('test-arranged-tree'),
	Listing(children: true, sortable: true),
	Types('sortable-test-parent', 'test-hierarchy-child'),
]
final class TestArrangedTreeCollection {}
