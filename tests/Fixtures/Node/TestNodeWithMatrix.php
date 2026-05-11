<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Field\Text;
use Celemas\Cms\Node\Contract\Title;
use Celemas\Cms\Schema\Label;
use Celemas\Cms\Schema\Required;
use Celemas\Cms\Schema\Translate;

#[Label('Test Node With Matrix')]
class TestNodeWithMatrix implements Title
{
	#[Label('Titel'), Required, Translate]
	protected Text $title;

	#[Label('My Matrix Field'), Required]
	protected TestMatrix $matrix;

	public function title(): string
	{
		return strip_tags($this->title->value()->unwrap());
	}
}
