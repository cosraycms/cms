<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;

final class NodeWithLegacyOrder
{
	private Text $heading;
	private Text $body;

	public function order(): array
	{
		return ['body', 'heading'];
	}
}
