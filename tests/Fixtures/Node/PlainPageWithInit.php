<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Init;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Route;

#[Label('Plain Page With Init')]
#[Route('/plain-page-with-init/{uid}')]
class PlainPageWithInit implements Init
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
