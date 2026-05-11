<?php

declare(strict_types=1);

namespace Celemas\Cms;

class RememberDetails
{
	public function __construct(
		#[\SensitiveParameter]
		public readonly Token $token,
		public readonly int $expires,
	) {}
}
