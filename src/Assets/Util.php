<?php

declare(strict_types=1);

namespace Cosray\Assets;

use Cosray\Exception\RuntimeException;
use Normalizer;
use Transliterator;

class Util
{
	/**
	 * Conservative lowercase slug of an uploaded filename, safe as URL
	 * path segment and pool basename. May return an empty string or a
	 * bare extension for names without transliterable characters.
	 */
	public static function slug(string $filename): string
	{
		$slug = Normalizer::normalize($filename, Normalizer::FORM_C) ?: $filename;
		$latin = Transliterator::create('Any-Latin; Latin-ASCII')?->transliterate($slug);

		if (is_string($latin)) {
			$slug = $latin;
		}

		$slug = mb_strtolower($slug);
		$slug = preg_replace('/\s+/u', '-', $slug) ?? '';
		$slug = preg_replace('/[^a-z0-9._-]/', '', $slug) ?? '';
		$slug = preg_replace('/-{2,}/', '-', $slug) ?? '';
		$slug = preg_replace('/\.{2,}/', '.', $slug) ?? '';

		// A leading dot survives so `Storage::key()` can keep the
		// extension when it swaps in the uid as stem.
		return rtrim(ltrim($slug, '-'), '-.');
	}

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
