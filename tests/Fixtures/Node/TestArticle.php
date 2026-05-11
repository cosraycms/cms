<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Field\Text;
use Celemas\Cms\Field\Textarea;
use Celemas\Cms\Node\Contract\Title;
use Celemas\Cms\Schema\Label;
use Celemas\Cms\Schema\Translate;

#[Label('Test Article')]
class TestArticle implements Title
{
	#[Label('Title')]
	#[Translate]
	public Text $title;

	#[Label('Content')]
	#[Translate]
	public Textarea $content;

	public function title(): string
	{
		return $this->title?->value()->unwrap() ?? 'Test Article';
	}
}
