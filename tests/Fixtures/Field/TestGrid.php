<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Field;

use Celemas\Cms\Field\Grid;
use Celemas\Cms\Schema\Columns;
use Celemas\Cms\Schema\Label;
use Celemas\Cms\Schema\Translate;

#[Label('Test Grid')]
#[Columns(12, 4)]
#[Translate]
class TestGrid extends Grid {}
