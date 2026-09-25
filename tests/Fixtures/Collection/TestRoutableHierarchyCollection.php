<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Schema\Blueprints;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Listing;
use Cosray\Schema\Types;
use Cosray\Tests\Fixtures\Node\OptionalParentPathRoutePage;

#[
	Label('Test routable hierarchy'),
	Handle('test-routable-hierarchy'),
	Listing(children: true),
	Types('optional-parent-path-route-page'),
	Blueprints(OptionalParentPathRoutePage::class),
]
final class TestRoutableHierarchyCollection {}
