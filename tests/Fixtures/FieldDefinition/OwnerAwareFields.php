<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\FieldDefinition;

use Cosray\Contract\Embedded;
use Cosray\Field\Text;

final class OwnerAwareFields implements Embedded
{
	private Text $title;

	public function __construct(
		private readonly OwnerAwareNode $node,
	) {}
}
