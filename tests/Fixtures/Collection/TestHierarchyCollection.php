<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Schema\Blueprints;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Listing;
use Cosray\Schema\Types;
use Cosray\Tests\Fixtures\Node\TestHierarchyParent;

#[
	Label('Test hierarchy'),
	Handle('test-hierarchy'),
	Listing(children: true),
	Types('test-hierarchy-parent', 'test-hierarchy-child'),
	Blueprints(TestHierarchyParent::class),
]
final class TestHierarchyCollection {}
