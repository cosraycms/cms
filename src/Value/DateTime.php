<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\DateTime\Codec;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Field;
use Cosray\Field\Owner;
use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;

class DateTime extends Value
{
	public const FORMAT = Codec::STORAGE_FORMAT;

	public readonly ?DateTimeImmutable $datetime;
	public readonly DateTimeZone $timezone;

	public function __construct(Owner $owner, Field $field, ValueContext $context)
	{
		parent::__construct($owner, $field, $context);

		$timezone = $this->meta('timezone', 'UTC');
		$this->timezone = Codec::timezone($timezone)
			?? throw new RuntimeException("Invalid timezone for field '{$this->fieldName}'");

		$value = $this->value();

		if (!is_string($value) || $value === '') {
			$this->datetime = null;

			return;
		}

		$this->datetime = $this->parse($value)
			?? throw new RuntimeException("Invalid date/time in field '{$this->fieldName}'");
	}

	public function __toString(): string
	{
		return $this->datetime === null ? '' : Codec::format($this->datetime);
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

	protected function parse(string $value): ?DateTimeImmutable
	{
		return Codec::parse($value)?->setTimezone($this->timezone);
	}

	protected function parseFormat(string $value, string $format): ?DateTimeImmutable
	{
		$date = DateTimeImmutable::createFromFormat('!' . $format, $value, $this->timezone);
		$errors = DateTimeImmutable::getLastErrors();

		if (
			$date === false
			|| $errors !== false
			&& (
				$errors['warning_count'] > 0
				|| $errors['error_count'] > 0
			)
			|| $date->format($format) !== $value
		) {
			return null;
		}

		return $date;
	}
}
