<?php

declare(strict_types=1);

namespace Cosray\Assets;

use Cosray\Exception\RuntimeException;

class Size
{
	public function __construct(
		public readonly int $firstDimension,
		public readonly ?int $secondDimension = null,
		public readonly ?int $cropMode = null,
	) {
		if ($firstDimension < 1) {
			throw new RuntimeException('Assets error: width must be >= 1');
		}

		if ($secondDimension && $secondDimension < 1) {
			throw new RuntimeException('Assets error: width must be >= 1');
		}
	}
}
