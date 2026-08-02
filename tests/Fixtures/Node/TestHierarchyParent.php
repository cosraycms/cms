<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Field\Text;
use Cosray\Schema\Children;
use Cosray\Schema\Label;
use Cosray\Schema\Translate;

#[Label('Hierarchy Parent')]
#[Children(TestHierarchyParent::class, TestHierarchyChild::class)]
class TestHierarchyParent implements Title
{
	#[Label('Title')]
	#[Translate]
	public Text $title;

	public function title(): string
	{
		return $this->title?->value()->unwrap() ?? 'Hierarchy Parent';
	}
}
