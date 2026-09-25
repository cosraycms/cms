<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Schema\Blueprints;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Types;
use Cosray\Tests\Fixtures\Node\TestNodeWithBlocks;

#[Label('Test blocks'), Handle('test-blocks'), Types('test-node-with-blocks'), Blueprints(TestNodeWithBlocks::class)]
final class TestBlocksCollection {}
