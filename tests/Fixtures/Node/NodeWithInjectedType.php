<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title as TitleContract;
use Cosray\Node\Type;
use Cosray\Schema\Label;

#[Label('Type Injected Node')]
class NodeWithInjectedType implements TitleContract
{
	public function __construct(
		private readonly Type $type,
	) {}

	public function title(): string
	{
		return $this->type->label;
	}

	public function typeHandle(): string
	{
		return $this->type->handle;
	}
}
