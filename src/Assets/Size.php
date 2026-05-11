<?php

declare(strict_types=1);

namespace Celemas\Cms\Assets;

use Celemas\Cms\Exception\RuntimeException;

class Size
{
	public function __construct(
		public readonly int $firstDimension,
		public readonly ?int $secondDimension = null,
		public readonly int|array|null $cropMode = null,
	) {
		if ($firstDimension < 1) {
			throw new RuntimeException('Assets error: width must be >= 1');
		}

		if ($secondDimension && $secondDimension < 1) {
			throw new RuntimeException('Assets error: width must be >= 1');
		}
	}
}
