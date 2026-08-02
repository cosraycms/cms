<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Contract\ViewContext;
use Cosray\Field\Text;
use Cosray\Node\Wrapper;
use Cosray\Schema\Label;
use Cosray\Schema\Render;

#[Label('View Context Page')]
#[Render('view-context')]
class ViewContextPage implements Title, ViewContext
{
	#[Label('Title')]
	public Text $title;

	public function title(): string
	{
		return $this->title?->value()->unwrap() ?? 'View Context';
	}

	/** @return array<string, mixed> */
	public function viewContext(Wrapper $node): array
	{
		// Reads through the wrapper it is handed, the way the template would.
		return [
			'greeting' => 'hello ' . $node->title(),
			'contextUid' => $node->meta->uid,
		];
	}
}
