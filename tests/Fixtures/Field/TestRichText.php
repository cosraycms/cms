<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Field;

use Celemas\Cms\Field\RichText;
use Celemas\Cms\Schema\Label;
use Celemas\Cms\Schema\Translate;

#[Label('Test RichText')]
#[Translate]
class TestRichText extends RichText {}
