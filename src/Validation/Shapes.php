<?php

declare(strict_types=1);

namespace Celemas\Cms\Validation;

use Celemas\Sire\Shape;

final class Shapes
{
	public static function create(): Shape
	{
		return new Shape()->validators(Validators::registry());
	}

	public static function list(): Shape
	{
		return Shape::list()->validators(Validators::registry());
	}
}
