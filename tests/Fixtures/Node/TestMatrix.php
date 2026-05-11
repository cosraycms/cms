<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Field\Grid;
use Celemas\Cms\Field\Matrix;
use Celemas\Cms\Field\Text;
use Celemas\Cms\Schema\Label;
use Celemas\Cms\Schema\Required;
use Celemas\Cms\Schema\Translate;

class TestMatrix extends Matrix
{
	#[Label('Titel'), Required, Translate]
	protected Text $title;

	#[Label('Inhalt'), Translate]
	protected Grid $content;
}
