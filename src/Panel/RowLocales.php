<?php

declare(strict_types=1);

namespace Cosray\Panel;

/**
 * Whether a typed repeater row carries its own locale tabs. A row owns
 * them when any visible sub-field renders one variant per locale, and
 * then switches all of them at once; the sub-field wrappers inside show
 * none. The controls listed here are the ones field/field.php renders
 * per locale.
 */
final class RowLocales
{
	private const array SWITCHABLE = ['text', 'textarea', 'iframe', 'element'];

	/** @param array<string, mixed> $type a row type descriptor */
	public static function owned(array $type, int $locales): bool
	{
		if ($locales < 2) {
			return false;
		}

		foreach ((array) ($type['fields'] ?? []) as $sub) {
			if (!is_array($sub) || ($sub['hidden'] ?? false) || !($sub['translate'] ?? false)) {
				continue;
			}

			$control = is_array($sub['control'] ?? null) ? $sub['control'] : [];

			if (in_array((string) ($control['name'] ?? ''), self::SWITCHABLE, true)) {
				return true;
			}
		}

		return false;
	}
}
