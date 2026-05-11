<?php

declare(strict_types=1);

namespace Celemas\Cms\Value;

use IntlDateFormatter;

class Date extends DateTime
{
	public const FORMAT = 'Y-m-d';

	public function localize(
		int $dateFormat = IntlDateFormatter::MEDIUM,
		int $timeFormat = IntlDateFormatter::NONE,
	): string {
		return parent::localize($dateFormat, $timeFormat);
	}
}
