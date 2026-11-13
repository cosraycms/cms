<?php

declare(strict_types=1);

namespace Cosray\Panel;

final class Icon
{
	/**
	 * @param string $class A part class of the component the icon sits in,
	 *                      for the rare rule that singles one icon out.
	 */
	public static function render(string $name, string $class = ''): string
	{
		if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $name) !== 1) {
			return '';
		}

		$path = __DIR__ . '/../../panel/icons/' . $name . '.svg';

		if (!is_file($path)) {
			return '';
		}

		$svg = file_get_contents($path);

		if ($svg === false) {
			return '';
		}

		$svg = preg_replace('/\A(\s*<svg\b[^>]*?)\s+class="[^"]*"/', '$1', $svg, 1) ?? $svg;
		$classes = trim('cms-icon ' . htmlspecialchars($class, ENT_QUOTES));

		return (
			preg_replace(
				'/<svg\b/',
				'<svg aria-hidden="true" focusable="false" class="' . $classes . '"',
				$svg,
				1,
			) ?? $svg
		);
	}
}
