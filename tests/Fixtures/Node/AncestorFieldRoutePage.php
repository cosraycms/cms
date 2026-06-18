<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;
use Cosray\Node\Contract\Title;
use Cosray\Schema\Label;
use Cosray\Schema\Route;
use Cosray\Schema\Translate;

#[Label('Ancestor Field Route Page')]
#[Route('/{parent(2).title}/{parent.countryCode|lowercase}-{title}')]
class AncestorFieldRoutePage implements Title
{
	#[Label('Title')]
	#[Translate]
	public Text $title;

	public function title(): string
	{
		return $this->title?->value()->unwrap() ?? 'Ancestor Field Route Page';
	}
}
