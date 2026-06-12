<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Field;

use Cosray\Field\Blocks;
use Cosray\Schema\Columns;
use Cosray\Schema\Label;
use Cosray\Schema\Translate;

#[Label('Test Blocks')]
#[Columns(12, 4)]
#[Translate]
class TestBlocks extends Blocks {}
