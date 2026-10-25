<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Permission;

#[Label('Test Internal Document'), Permission(['read' => 'read-nodes'])]
final class TestInternalDocument implements Title
{
	#[Label('Title')]
	public Text $title;

	public function title(): string
	{
		return $this->title->value()->raw();
	}
}
