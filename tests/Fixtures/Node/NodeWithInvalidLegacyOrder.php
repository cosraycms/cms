<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;

final class NodeWithInvalidLegacyOrder
{
	private Text $heading;

	public function order(): array
	{
		return ['heading', 'missing'];
	}
}
