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

		$fileHandle = fopen($fileName, 'rb');

		if ($fileHandle === false) {
			throw new RuntimeException('File could not be opened: ' . $fileName);
		}

		try {
			if (fread($fileHandle, 3) !== 'GIF') {
				return false;
			}

			rewind($fileHandle);
			$frameCount = 0;

			while (!feof($fileHandle)) {
				$chunk = fread($fileHandle, 1024 * 100);

				if ($chunk === false) {
					return false;
				}

				$frameCount += substr_count($chunk, "\x00\x21\xF9\x04");

				if ($frameCount > 1) {
					return true;
				}
			}

			return false;
		} finally {
			fclose($fileHandle);
		}
	}
}
