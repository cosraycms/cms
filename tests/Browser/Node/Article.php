<?php

declare(strict_types=1);

namespace Cosray\Tests\Browser\Node;

use Cosray\Contract\Title;
use Cosray\Field\Date;
use Cosray\Field\Text;
use Cosray\Field\Textarea;
use Cosray\Schema\Label;
use Cosray\Schema\Render;
use Cosray\Schema\Required;
use Cosray\Schema\Route;

#[Label('Article'), Route('/{title}'), Render('article')]
class Article implements Title
{
	#[Label('Title'), Required]
	protected Text $title;

	#[Label('Date'), Required]
	protected Date $date;

	#[Label('Teaser')]
	protected Textarea $teaser;

	public function title(): string
	{
		return $this->title?->value()->raw() ?? '';
	}
}
