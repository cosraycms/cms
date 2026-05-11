<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Field\Text;
use Celemas\Cms\Node\Contract\Title;
use Celemas\Cms\Schema\Label;
use Celemas\Cms\Schema\Route;
use Celemas\Cms\Schema\Translate;

#[Label('Test Page')]
#[Route('/test/{uid}')]
class TestPage implements Title
{
	#[Label('Title')]
	#[Translate]
	public Text $title;

	public function title(): string
	{
		return $this->title?->value()->unwrap() ?? 'Test Page';
	}
}
