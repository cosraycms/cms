<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Field\Link;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Translate;

#[Label('Test Link Document')]
class TestLinkDocument implements Title
{
	#[Label('Title')]
	public Text $title;

	#[Label('Target'), Required]
	public Link $target;

	#[Label('Download'), Translate]
	public Link $download;

	public function title(): string
	{
		return $this->title?->value()->raw() ?? 'Test Link Document';
	}
}
