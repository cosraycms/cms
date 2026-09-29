<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Listing;
use Cosray\Schema\Types;

/** A flat listing with an order of its own, children of any parent included. */
#[
	Label('Test arranged'),
	Handle('test-arranged'),
	Listing(sortable: true),
	Types('test-hierarchy-child'),
]
final class TestArrangedCollection {}
