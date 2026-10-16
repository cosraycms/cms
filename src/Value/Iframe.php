<?php

declare(strict_types=1);

namespace Cosray\Value;

use Override;

/**
 * The stored embed code, unescaped: an iframe field holds trusted
 * editor input by design and is unusable as an embed otherwise.
 */
class Iframe extends Text
{
	#[Override]
	public function __toString(): string
	{
		return $this->raw();
	}
}
