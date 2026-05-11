<?php

declare(strict_types=1);

namespace Celemas\Cms\Assets;

use Celemas\Cms\Exception\RuntimeException;

class Util
{
	public static function isAnimatedGif(string $fileName): bool
	{
		// Check if the file exists
		if (!file_exists($fileName)) {
			throw new RuntimeException('File does not exist: ' . $fileName);
		}

		// Open the file
		$fileHandle = fopen($fileName, 'rb');

		if (!$fileHandle) {
			throw new RuntimeException('File could not be opened: ' . $fileName);
		}

		// Read the first few bytes of the file
		$header = fread($fileHandle, 3);

		// Close the file handle
		fclose($fileHandle);

		// Check if the file header matches the GIF magic number
		if ($header === 'GIF') {
			$fileHandle = fopen($fileName, 'rb');
			$frameCount = 0;

			while (!feof($fileHandle) && $frameCount < 2) {
				$chunk = fread($fileHandle, 1024 * 100); // read 100kb at a time
				$frameCount += substr_count($chunk, "\x00\x21\xF9\x04");

				if ($frameCount > 1) {
					fclose($fileHandle);

					return true;
				}
			}
		}

		return false;
	}
}
