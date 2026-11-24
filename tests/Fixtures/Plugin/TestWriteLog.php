<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Plugin;

use Cosray\Context;
use Cosray\Contract\NodeListener;
use Cosray\Node\Write;

final class TestWriteLog implements NodeListener
{
	/** @var list<Write> */
	public static array $writes = [];

	public function __construct(
		public readonly Context $context,
	) {}

	public function written(Write $write): void
	{
		self::$writes[] = $write;
	}
}
