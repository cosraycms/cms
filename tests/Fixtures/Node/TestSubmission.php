<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Record;

#[Label('Test Submission'), Record]
class TestSubmission implements Title
{
	#[Label('Title')]
	public Text $title;

	public function title(): string
	{
		return $this->title?->value()->raw() ?? 'Test Submission';
	}
}
