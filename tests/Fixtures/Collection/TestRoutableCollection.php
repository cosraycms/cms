<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Schema\Blueprints;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Types;
use Cosray\Tests\Fixtures\Node\ParentPathRoutePage;

#[
	Label('Test routable'),
	Handle('test-routable'),
	Types('parent-path-route-page'),
	Blueprints(ParentPathRoutePage::class),
]
final class TestRoutableCollection {}
