<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Node\Contract\Title as TitleContract;
use Celemas\Cms\Node\Type;
use Celemas\Cms\Schema\Label;

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
