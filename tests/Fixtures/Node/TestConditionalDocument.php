<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Checkbox;
use Cosray\Field\Text;
use Cosray\Node\Contract\Title;
use Cosray\Schema\Label;
use Cosray\Schema\When;
use Cosray\Tests\Fixtures\Field\TestStyledText;

#[Label('Conditional Document')]
class TestConditionalDocument implements Title
{
	#[Label('Title')]
	public Text $title;

	#[Label('Styled')]
	public TestStyledText $styled;

	#[Label('Multi Day')]
	public Checkbox $multiDay;

	#[Label('End Date')]
	#[When('multiDay')]
	public Text $endDate;

	#[Label('Layout Hint')]
	#[When('title', 'hero')]
	public Text $layoutHint;

	public function title(): string
	{
		return $this->title?->value()->unwrap() ?? 'Conditional Document';
	}
}
