<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Plugin;

use Cosray\Field\Field;
use Cosray\Field\Schema\Handler;

final class TestBadgeHandler extends Handler
{
	public function apply(object $meta, Field $field): void {}

	public function properties(object $meta, Field $field): array
	{
		assert($meta instanceof TestBadge);

		return ['badge' => $meta->badge];
	}
}
