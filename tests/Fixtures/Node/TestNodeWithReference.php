<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Reference;
use Cosray\Field\Text;
use Cosray\Node\Contract\Title;
use Cosray\Schema\Filter;
use Cosray\Schema\Label;
use Cosray\Schema\Limit;
use Cosray\Schema\Targets;

#[Label('Test Node With Reference')]
class TestNodeWithReference implements Title
{
	#[Label('Title')]
	protected Text $title;

	#[Label('Related'), Targets(TestArticle::class), Filter("type = 'test-article'")]
	protected Reference $related;

	#[Label('Author'), Limit(max: 1)]
	protected Reference $author;

	public function title(): string
	{
		return $this->title->value()->unwrap() ?? '';
	}
}
