<?php

declare(strict_types=1);

namespace Cosray\Value;

use DateTimeImmutable;
use IntlDateFormatter;

class Time extends DateTime
{
	public const FORMAT = 'H:i';

	public function __toString(): string
	{
		return $this->format(static::FORMAT);
	}

	public function localize(
		int $dateFormat = IntlDateFormatter::NONE,
		int $timeFormat = IntlDateFormatter::SHORT,
	): string {
		return parent::localize($dateFormat, $timeFormat);
	}

	protected function parse(string $value): ?DateTimeImmutable
	{
		return $this->parseFormat($value, static::FORMAT);
	}
}
