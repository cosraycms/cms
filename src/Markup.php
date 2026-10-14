<?php

declare(strict_types=1);

namespace Cosray;

use Override;
use Stringable;

/**
 * HTML that is safe to print as is. Cosray's Boiler renderers trust this
 * class, so templates print it without escaping it again.
 */
final readonly class Markup implements Stringable
{
	public function __construct(
		public string $html,
	) {}

	#[Override]
	public function __toString(): string
	{
		return $this->html;
	}
}
