<?php

declare(strict_types=1);

namespace Cosray\Assets;

use Cosray\Assets\Backend\Backend;
use Cosray\Assets\Backend\Gd;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Util\Path;

/**
 * Materializes renditions for the cache fallback route. Everything else only
 * builds URLs (`Asset::path()`/`sizePath()`).
 */
final class Renditions
{
	private readonly string $assetsDir;
	private readonly string $cacheDir;

	public function __construct(
		Config $config,
		private readonly Backend $backend = new Gd(),
	) {
		$publicDir = realpath($config->path->public);

		if ($publicDir === false) {
			throw new RuntimeException('Public directory does not exist: ' . $config->path->public);
		}

		$this->assetsDir = Path::below($publicDir, $config->path->assets);
		$this->cacheDir = Path::below($publicDir, $config->path->cache);
	}

	/** The asset's file; it throws when there is none. */
	public function original(Asset $asset): string
	{
		return Path::inside($this->assetsDir, $asset->key, checkIsFile: true);
	}

	/**
	 * Writes the rendition next to its native URL unless a current one is there.
	 *
	 * @param string $file The rendition's name, which the caller has checked
	 * @return string Its path
	 */
	public function render(Asset $asset, string $file, SizeSpec $spec, ?Transform $transform = null): string
	{
		$original = $this->original($asset);
		$target = "{$this->cacheDir}/" . dirname($asset->key) . "/{$file}";

		if (is_file($target) && filemtime($original) <= filemtime($target)) {
			return $target;
		}

		Path::ensureDirectory(dirname($target));
		// Concurrent first requests must never see a half-written file, so
		// each one writes its own temporary file and moves it into place.
		// Threads of one worker process share a PID, hence the random name.
		$tmp = "{$target}.tmp-" . bin2hex(random_bytes(6));

		try {
			$this->write($original, $tmp, $spec, $transform);

			if (!rename($tmp, $target)) {
				throw new RuntimeException('Assets error: could not move rendition into place');
			}
		} finally {
			if (is_file($tmp)) {
				unlink($tmp);
			}
		}

		return $target;
	}

	private function write(string $original, string $tmp, SizeSpec $spec, ?Transform $transform): void
	{
		// A backend keeps only the first frame, so an animated GIF is
		// materialized as a copy of the original instead.
		if (Util::isAnimatedGif($original)) {
			if (!copy($original, $tmp)) {
				throw new RuntimeException('Assets error: could not copy animated gif');
			}

			return;
		}

		[$width, $height] = Dimensions::ofFile($original)
			?? throw new RuntimeException('Assets error: not a readable image');

		$this->backend->render($original, $tmp, Recipe::for($width, $height, $spec, $transform));
	}
}
