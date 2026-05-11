<?php

declare(strict_types=1);

namespace Celemas\Cms\Validation;

final class Prepare
{
	public static function nullAsEmpty(mixed $value): mixed
	{
		return $value ?? [];
	}
}
