<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Field;

use Celemas\Cms\Field\Text;
use Celemas\Cms\Schema\Label;
use Celemas\Cms\Schema\Translate;

#[Label('Test Text')]
#[Translate]
class TestText extends Text {}
