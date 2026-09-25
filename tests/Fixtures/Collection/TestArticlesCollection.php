<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Types;

#[Label('Test articles'), Handle('test-articles'), Types('test-article')]
final class TestArticlesCollection {}
