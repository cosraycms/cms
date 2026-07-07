<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Reference;
use Cosray\Field\Text;
use Cosray\Node\Contract\Title;
use Cosray\Schema\Label;
use Cosray\Schema\Limit;
use Cosray\Schema\Pick;

#[Label('Test Node With Reference')]
class TestNodeWithReference implements Title
{
	#[Label('Title')]
	protected Text $title;

	#[Label('Related'), Pick(TestArticle::class)]
	protected Reference $related;

	// No #[Pick]: any non-deleted node is pickable (single).
	#[Label('Author'), Limit(max: 1)]
	protected Reference $author;

	// Publication gate: only published nodes are pickable.
	#[Label('Live'), Pick(published: true)]
	protected Reference $live;

	// Type constraint via the finder DSL rather than the typed param.
	#[Label('Where'), Pick(where: "type = 'test-article'")]
	protected Reference $wherePick;

	public function title(): string
	{
		return $this->title->value()->unwrap() ?? '';
	}
}
