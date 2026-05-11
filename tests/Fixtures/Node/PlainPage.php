<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Field\Text;
use Celemas\Cms\Node\Contract\Title as TitleContract;
use Celemas\Cms\Schema\FieldOrder;
use Celemas\Cms\Schema\Label;
use Celemas\Cms\Schema\Route;
use Celemas\Cms\Schema\Title;
use Celemas\Cms\Schema\Translate;

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
