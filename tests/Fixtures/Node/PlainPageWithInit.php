<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Field\Text;
use Celemas\Cms\Node\Contract\HasInit;
use Celemas\Cms\Schema\Label;
use Celemas\Cms\Schema\Route;

#[Label('Plain Page With Init')]
#[Route('/plain-page-with-init/{uid}')]
class PlainPageWithInit implements HasInit
{
	#[Label('Title')]
	protected Text $title;

	public bool $initialized = false;

	public function init(): void
	{
		$this->initialized = true;
	}

	public function title(): string
	{
		return $this->title?->value()->unwrap() ?? '';
	}
}
