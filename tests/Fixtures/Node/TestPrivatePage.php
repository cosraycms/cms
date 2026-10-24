<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Permission;
use Cosray\Schema\Route;

#[Label('Test Private Page'), Route('/private/{uid}'), Permission(['read' => 'read-nodes'])]
final class TestPrivatePage implements Title
{
	#[Label('Title')]
	public Text $title;

	public function title(): string
	{
		return $this->title->value()->raw();
	}
}
