<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Field\Reference;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Route;
use Cosray\Schema\Translate;

#[Label('Test Draft Page')]
#[Route('/draft/{uid}')]
class TestDraftPage implements Title
{
	#[Label('Title')]
	#[Translate]
	public Text $title;

	#[Label('Related')]
	public Reference $related;

	public function title(): string
	{
		return $this->title?->value()->unwrap() ?? 'Test Draft Page';
	}
}
