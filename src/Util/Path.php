<?php

declare(strict_types=1);

namespace Cosray\Util;

use Cosray\Exception\RuntimeException;

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

	/**
	 * Resolves a directory configured below `$parent`. Unlike `inside()`, the
	 * directory may resolve elsewhere through a symlink, such as uploads shared
	 * between deployed releases; only `..` segments in `$child` are refused.
	 */
	public static function below(string $parent, string $child): string
	{
		if (in_array('..', explode('/', str_replace('\\', '/', $child)), true)) {
			throw new RuntimeException('Path leaves its parent directory: ' . $child);
		}

		$dir = rtrim($parent, '\\/') . DIRECTORY_SEPARATOR . ltrim($child, '\\/');
		$path = realpath($dir);

		if ($path === false || !is_dir($path)) {
			throw new RuntimeException('Directory does not exist: ' . $dir);
		}

		return $path;
	}

	/**
	 * Creates the directory and its parents unless it exists. Concurrent
	 * requests may create it at the same time; only a directory that still
	 * does not exist afterwards is an error.
	 */
	public static function ensureDirectory(string $dir, int $mode = 0o755): void
	{
		if (is_dir($dir)) {
			return;
		}

		// mkdir() warns when another request created the directory first,
		// and the request's error handler would turn that into an exception.
		set_error_handler(static fn(): bool => true);

		try {
			$created = mkdir($dir, $mode, recursive: true);
		} finally {
			restore_error_handler();
		}

		if (!$created && !is_dir($dir)) {
			throw new RuntimeException('Could not create directory: ' . $dir);
		}
	}
}
