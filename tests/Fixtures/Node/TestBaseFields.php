<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Embedded;
use Cosray\Contract\HasInit;
use Cosray\Contract\Title;
use Cosray\Field\Text;
use Cosray\Node\Type;
use Cosray\Schema\Label;
use Cosray\Schema\Title as TitleAttribute;
use Cosray\Schema\Translate;

#[Label('Base fields')]
final class TestBaseFields implements Embedded, HasInit, Title
{
	#[TitleAttribute]
	#[Label('Embedded title')]
	#[Translate]
	protected Text $title;

	#[Label('Embedded body')]
	protected Text $body;

	public bool $initialized = false;

	public function __construct(
		private readonly Type $type,
	) {}

	public function init(): void
	{
		$this->initialized = true;
	}

	public function title(): string
	{
		return $this->title->value()->unwrap() ?? '';
	}

	public function typeHandle(): string
	{
		return $this->type->handle;
	}
}
