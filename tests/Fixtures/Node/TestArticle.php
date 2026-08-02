<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Field\Text;
use Cosray\Field\Textarea;
use Cosray\Schema\Label;
use Cosray\Schema\Translate;

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
