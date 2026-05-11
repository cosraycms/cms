<?php

declare(strict_types=1);

namespace Celemas\Cms\Util;

use Celemas\Cms\Exception\RuntimeException;

class Path
{
	public static function inside(string $parent, string $child, bool $checkIsFile = false): string
	{
		$parent = realpath($parent);

		if (!$parent) {
			throw new RuntimeException('Parent directory does not exist.');
		}

		$path = realpath(rtrim($parent, '\\/') . DIRECTORY_SEPARATOR . ltrim($child, '\\/'));

		if (!$path || strncmp($path, $parent, strlen($parent)) !== 0) {
			throw new RuntimeException(
				'File or directory does not exist or is not in the expected location.',
			);
		}

		if ($checkIsFile && !is_file($path)) {
			throw new RuntimeException('Path is not a file: ' . $path);
		}

		return $path;
	}
}
