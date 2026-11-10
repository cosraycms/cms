<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Listing;
use Cosray\Schema\Types;

#[Label('Test submissions'), Handle('test-submissions'), Types('test-submission'), Listing(published: false)]
final class TestSubmissionsCollection {}
