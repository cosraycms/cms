<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Field\Text;
use Cosray\Schema\Drafts;
use Cosray\Schema\Label;

#[Label('Test Setting'), Drafts(false)]
class TestSetting implements Title
{
	#[Label('Title')]
	public Text $title;

	public function title(): string
	{
		return $this->title?->value()->raw() ?? 'Test Setting';
	}
}
