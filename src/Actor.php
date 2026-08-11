<?php

declare(strict_types=1);

namespace Cosray;

use ValueError;

final readonly class Actor
{
	public function __construct(
		public int $id,
	) {
		if ($id < 1) {
			throw new ValueError('A content actor id must be positive');
		}
	}

	public static function system(): self
	{
		return new self(1);
	}
}
