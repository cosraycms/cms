<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Field\Field;
use Cosray\Field\Owner;
use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;

class DateTime extends Value
{
	public const FORMAT = 'Y-m-d H:i:s';

	public readonly ?DateTimeImmutable $datetime;
	public readonly ?DateTimeZone $timezone;

	public function __construct(Owner $owner, Field $field, ValueContext $context)
	{
		parent::__construct($owner, $field, $context);

		$timezone = $this->meta('timezone');
		$this->timezone = is_string($timezone) && $timezone !== '' ? new DateTimeZone($timezone) : null;

		$value = $this->value();

		if (is_string($value) && $value !== '') {
			$this->datetime = DateTimeImmutable::createFromFormat(
				static::FORMAT,
				$value,
				$this->timezone,
			);
		} else {
			$this->datetime = null;
		}
	}

	public function __toString(): string
	{
		return $this->format(static::FORMAT);
	}

	public function isset(): bool
	{
		return isset($this->datetime) ? true : false;
	}

	public function unwrap(): ?DateTimeImmutable
	{
		return $this->datetime;
	}

	public function format(string $format): string
	{
		if ($this->datetime) {
			return $this->datetime->format($format);
		}

		return '';
	}

	public function localize(
		int $dateFormat = IntlDateFormatter::MEDIUM,
		int $timeFormat = IntlDateFormatter::MEDIUM,
	): string {
		if ($this->datetime) {
			$formatter = new IntlDateFormatter(
				$this->locale->id,
				$dateFormat,
				$timeFormat,
				$this->timezone,
			);

			return $formatter->format($this->datetime->getTimestamp());
		}

		return '';
	}

	public function json(): mixed
	{
		return $this->__toString();
	}
}
