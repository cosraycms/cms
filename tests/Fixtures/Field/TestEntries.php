<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Field;

use Cosray\Field\Blocks;
use Cosray\Field\Entries;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Translate;
use Cosray\Schema\TranslateMode;

#[Label('Test Entries')]
class TestEntries extends Entries
{
	#[Label('Titel'), Required, Translate]
	protected Text $title;

	#[Label('Inhalt'), Translate(TranslateMode::Asymmetric)]
	protected Blocks $content;
}
