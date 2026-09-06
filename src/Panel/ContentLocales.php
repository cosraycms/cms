<?php

declare(strict_types=1);

namespace Cosray\Panel;

/** Whether a node field tree contains content controlled by an editing locale. */
final class ContentLocales
{
	private const array SWITCHABLE = ['text', 'textarea', 'iframe', 'youtube', 'element'];

	/** @param list<array<string, mixed>> $fields */
	public static function used(array $fields, int $locales): bool
	{
		if ($locales < 2) {
			return false;
		}

		foreach ($fields as $field) {
			if (is_array($field) && self::field($field)) {
				return true;
			}
		}

		return false;
	}

	/** @param array<string, mixed> $field */
	private static function field(array $field): bool
	{
		if ($field['hidden'] ?? false) {
			return false;
		}

		$control = is_array($field['control'] ?? null) ? $field['control'] : [];
		$name = (string) ($control['name'] ?? '');
		$translate = (bool) ($field['translate'] ?? false);

		if ($translate && in_array($name, self::SWITCHABLE, true)) {
			return true;
		}

		if ($translate && $name === 'blocks' && ($field['translateMode'] ?? null) === 'asymmetric') {
			return true;
		}

		$types = match ($name) {
			'entries' => $control['props']['entryTypes'] ?? [],
			'blocks' => $control['props']['blockTypes'] ?? [],
			default => [],
		};

		foreach (is_array($types) ? $types : [] as $type) {
			if (is_array($type) && self::used((array) ($type['fields'] ?? []), 2)) {
				return true;
			}
		}

		return false;
	}
}
