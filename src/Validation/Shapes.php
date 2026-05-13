<?php

declare(strict_types=1);

namespace Celemas\Cms\Validation;

use Celemas\Sire\Extra;
use Celemas\Sire\Shape;

final class Shapes
{
	public static function create(): Shape
	{
		return self::configure(new Shape());
	}

	public static function list(): Shape
	{
		return self::configure(Shape::list());
	}

	private static function configure(Shape $shape): Shape
	{
		return $shape
			->rules(Validators::registry())
			->extra(Extra::Allow);
	}
}
