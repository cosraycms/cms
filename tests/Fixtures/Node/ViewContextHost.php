<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Render;

#[Label('View Context Host')]
#[Render('view-context-host')]
class ViewContextHost implements Title
{
	#[Label('Title')]
	public Text $title;

	public function title(): string
	{
		return 'Host';
	}
}
