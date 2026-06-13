<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;
use Cosray\Node\Contract\Title;
use Cosray\Schema\Label;
use Cosray\Schema\Required;

#[Label('Test Node With Entries')]
class TestNodeWithEntries implements Title
{
	#[Label('Title'), Required]
	protected Text $title;

	#[Label('My Entries Field'), Required]
	protected TestEntries $entries;

	public function title(): string
	{
		return $this->title->value()->unwrap() ?? '';
	}
}
