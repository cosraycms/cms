<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title as TitleContract;
use Cosray\Field\Text;
use Cosray\Schema\FieldOrder;
use Cosray\Schema\Label;
use Cosray\Schema\Route;
use Cosray\Schema\Title;
use Cosray\Schema\Translate;

#[Label('Plain Page')]
#[Route('/plain-page/{uid}')]
#[Title('heading')]
#[FieldOrder('heading', 'body')]
class PlainPage implements TitleContract
{
	#[Label('Heading')]
	#[Translate]
	protected Text $heading;

	#[Label('Body')]
	#[Translate]
	protected Text $body;

	public function title(): string
	{
		return $this->heading?->value()->unwrap() ?? 'Untitled';
	}
}
