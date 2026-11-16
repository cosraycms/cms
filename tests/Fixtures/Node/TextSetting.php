<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;
use Cosray\Schema\Drafts;
use Cosray\Schema\Translate;

#[Drafts(false)]
class TextSetting
{
	#[Translate]
	protected Text $title;

	#[Translate]
	protected Text $slogan;
}
