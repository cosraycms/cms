<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Translate
{
	public function __construct(
		public TranslateMode $mode = TranslateMode::Symmetric,
	) {}
}
