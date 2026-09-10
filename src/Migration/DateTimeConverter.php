<?php

declare(strict_types=1);

namespace Cosray\Migration;

use Cosray\DateTime\Codec;
use Cosray\Exception\RuntimeException;
use Cosray\Field\DateTime;
use Cosray\Field\Field;
use DateTimeZone;

final class DateTimeConverter
{
	public function convert(array $data, string $path = 'content'): array
	{
		if ($this->isDateTime($data)) {
			return $this->field($data, $path);
		}

		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$data[$key] = $this->convert($value, $path . '.' . $key);
			}
		}

		return $data;
	}

	private function isDateTime(array $data): bool
	{
		$type = $data['type'] ?? null;

		if (!is_string($type)) {
			return false;
		}

		// History snapshots can retain aliases and retired package namespaces.
		return $type === 'datetime' || $type === DateTime::class || str_ends_with($type, '\\Field\\DateTime');
	}

	private function field(array $data, string $path): array
	{
		$value = $data['value'] ?? null;

		if (is_array($value)) {
			foreach ($value as $locale => $datetime) {
				$value[$locale] = $this->value(
					$datetime,
					$this->timezone($data, is_string($locale) ? $locale : Field::NEUTRAL_LOCALE, $path),
					$path . '.value.' . $locale,
				);
			}
		} else {
			$value = $this->value(
				$value,
				$this->timezone($data, Field::NEUTRAL_LOCALE, $path),
				$path . '.value',
			);
		}

		$data['value'] = $value;

		return $data;
	}

	private function value(mixed $value, DateTimeZone $timezone, string $path): mixed
	{
		if ($value === null || $value === '') {
			return $value;
		}

		if (!is_string($value)) {
			throw new RuntimeException("Invalid date/time value at {$path}");
		}

		return (
			Codec::fromLegacy($value, $timezone)
				?? throw new RuntimeException("Invalid date/time value '{$value}' at {$path}")
		);
	}

	private function timezone(array $data, string $locale, string $path): DateTimeZone
	{
		$value = $data['meta']['timezone'] ?? $data['timezone'] ?? null;

		if (is_array($value)) {
			$value = $value[$locale] ?? $value[Field::NEUTRAL_LOCALE] ?? null;
		}

		if ($value === null || $value === '') {
			return Codec::utc();
		}

		return (
			Codec::timezone($value)
				?? throw new RuntimeException("Invalid timezone at {$path}.meta.timezone.{$locale}")
		);
	}
}
