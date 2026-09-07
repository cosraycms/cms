<?php

declare(strict_types=1);

namespace Cosray\Panel;

final class Icon
{
	public static function render(string $name): string
	{
		if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $name) !== 1) {
			return '';
		}

		$path = __DIR__ . '/../../panel/icons/' . $name . '.svg';

		if (!is_file($path)) {
			return '';
		}

		return str_replace(
			['<svg ', 'class="bi '],
			['<svg aria-hidden="true" focusable="false" ', 'class="cms-icon bi '],
			file_get_contents($path),
		);
	}
}
