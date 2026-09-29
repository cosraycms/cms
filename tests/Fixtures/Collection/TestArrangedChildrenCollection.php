<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Listing;
use Cosray\Schema\Types;

/** Top level sorted by columns, the parents' children arranged by hand. */
#[
	Label('Test arranged children'),
	Handle('test-arranged-children'),
	Listing(children: true),
	Types('sortable-test-parent', 'test-hierarchy-child'),
]
final class TestArrangedChildrenCollection {}
