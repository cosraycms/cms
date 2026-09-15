<?php

declare(strict_types=1);

namespace Cosray\Exception;

use Celema\Core\Exception\HttpForbidden;

final class ReadDenied extends HttpForbidden
{
	public function __construct(
		public readonly string $permission,
	) {
		parent::__construct();
	}
}
