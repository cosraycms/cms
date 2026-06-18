<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;
use Cosray\Node\Contract\Title;
use Cosray\Schema\Label;
use Cosray\Schema\Route;
use Cosray\Schema\Translate;

#[Label('Transformed Route Page')]
#[Route('/stations/{parent.countryCode|lowercase}-{parent.title}/{title}')]
class TransformedRoutePage implements Title
{
	#[Label('Title')]
	#[Translate]
	public Text $title;

	public function title(): string
	{
		return $this->title?->value()->unwrap() ?? 'Transformed Route Page';
	}
}
