<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Blocks;
use Cosray\Field\Matrix;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Translate;

class TestMatrix extends Matrix
{
	#[Label('Titel'), Required, Translate]
	protected Text $title;

	#[Label('Inhalt'), Translate]
	protected Blocks $content;
}
