<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Plugin;

use Cosray\Plugin\Plugin;
use Cosray\Plugin\Registrar;

/**
 * A plugin that cannot be constructed without arguments: registering it
 * by class name must fail with guidance, a pre-built instance loads.
 */
final class TestParameterizedPlugin implements Plugin
{
	public function __construct(
		private readonly string $currency,
	) {}

	public function id(): string
	{
		return 'test-parameterized';
	}

	public function register(Registrar $cms): void
	{
		$cms->register('test-parameterized.currency', new class($this->currency) {
			public function __construct(
				public readonly string $currency,
			) {}
		});
	}
}
