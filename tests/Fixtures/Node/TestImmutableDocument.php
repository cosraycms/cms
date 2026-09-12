<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Field\Checkbox;
use Cosray\Field\Text;
use Cosray\Schema\Immutable;
use Cosray\Schema\Label;

#[Label('Test Immutable Document')]
class TestImmutableDocument implements Title
{
	#[Label('Title')]
	public Text $title;

	#[Label('Reference')]
	#[Immutable]
	public Text $reference;

	#[Label('Featured')]
	#[Immutable]
	public Checkbox $featured;

	public function title(): string
	{
		return $this->title?->value()->unwrap() ?? 'Test Immutable Document';
	}
}
