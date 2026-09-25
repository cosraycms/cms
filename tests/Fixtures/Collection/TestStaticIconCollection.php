<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Schema\Handle;
use Cosray\Schema\Icon;
use Cosray\Schema\Label;
use Cosray\Schema\Types;

#[Label('Static icon'), Handle('test-static-icon'), Icon('bi:archive'), Types('test-article')]
final class TestStaticIconCollection {}
