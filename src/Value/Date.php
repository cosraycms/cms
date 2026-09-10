<?php

declare(strict_types=1);

namespace Cosray\Value;

use DateTimeImmutable;
use IntlDateFormatter;

class Date extends DateTime
{
	public const FORMAT = 'Y-m-d';

	public function __toString(): string
	{
		return $this->format(static::FORMAT);
	}

	public function localize(
		int $dateFormat = IntlDateFormatter::MEDIUM,
		int $timeFormat = IntlDateFormatter::NONE,
	): string {
		return parent::localize($dateFormat, $timeFormat);
	}

	protected function parse(string $value): ?DateTimeImmutable
	{
		return $this->parseFormat($value, static::FORMAT);
	}
}
