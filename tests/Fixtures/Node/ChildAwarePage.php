<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Celema\Core\Response;
use Cosray\Contract\HttpPost;
use Cosray\Contract\Title;
use Cosray\Contract\ViewContext;
use Cosray\Field\Text;
use Cosray\Node\View;
use Cosray\Node\Wrapper;
use Cosray\Schema\Label;
use Cosray\Schema\Render;

#[Label('Child Aware Page')]
#[Render('child-aware')]
class ChildAwarePage implements HttpPost, Title, ViewContext
{
	#[Label('Title')]
	public Text $title;

	public function __construct(
		private readonly View $view,
	) {}

	public function title(): string
	{
		return $this->title?->value()->unwrap() ?? 'Child Aware';
	}

	/** @return array<string, mixed> */
	public function viewContext(Wrapper $node): array
	{
		// children() needs a fully equipped wrapper; the renderer hands one over.
		return [
			'childCount' => count(iterator_to_array($node->children())),
			'note' => 'default',
		];
	}

	public function httpPost(): Response
	{
		// The explicit context wins over viewContext().
		return $this->view->render(['note' => 'posted']);
	}
}
