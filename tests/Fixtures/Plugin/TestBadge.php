<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Plugin;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class TestBadge
{
	public function __construct(
		public readonly string $badge,
	) {}
}
