<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Types;

#[Label('Test mixed full text'), Handle('test-fulltext-mixed'), Types('fulltext-page', 'fulltext-document')]
final class TestFulltextMixedCollection {}
