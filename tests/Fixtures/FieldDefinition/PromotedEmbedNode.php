<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\FieldDefinition;

use Cosray\Tests\Fixtures\Node\TestBaseFields;

final class PromotedEmbedNode
{
	public function __construct(
		private TestBaseFields $baseFields,
	) {}
}
