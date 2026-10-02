<?php

declare(strict_types=1);

namespace Cosray\Assets;

use Cosray\Exception\RuntimeException;
use Cosray\Util\Path;
use Gumlet\ImageResize;
use Gumlet\ImageResizeException;

/**
 * Materializes renditions for the cache fallback route. Everything else
 * only builds URLs (`Asset::path()`/`sizePath()`); this is the single
 * place that touches the resize pipeline.
 */
class Image
{
	public readonly string $relativeFile;
	public readonly string $file;
	protected ?string $cacheFile = null;

	public function __construct(
		protected readonly Assets $assets,
		string $file,
	) {
		$this->file = Path::inside($assets->assetsDir, $file, checkIsFile: true);
		$this->isResizable();
		$this->relativeFile = substr($this->file, strlen($assets->assetsDir));
	}

	public function path(): string
	{
		return $this->cacheFile ?: $this->file;
	}

	public function isResizable(): bool
	{
		return match (mime_content_type($this->file)) {
			'image/gif', 'image/jpeg', 'image/png', 'image/webp' => true,
			default => false,
		};
	}

	/**
	 * Materialize the rendition carrying the given size name next to its
	 * native URL.
	 */
	public function resize(
		Size $size,
		ResizeMode $mode,
		bool $enlarge,
		?int $quality,
		string $name,
	): static {
		if (!$this->isResizable()) {
			return $this;
		}

		$this->cacheFile = $this->getCacheFilePath($name);

		if (!is_file($this->cacheFile) || filemtime($this->file) > filemtime($this->cacheFile)) {
			$this->createCacheFile($size, $mode, $enlarge, $quality);
		}

		return $this;
	}

	public function get(): ImageResize
	{
		return new ImageResize($this->file);
	}

	protected function createCacheFile(
		Size $size,
		ResizeMode $mode,
		bool $enlarge,
		?int $quality,
	): void {
		// Concurrent first requests must never see a half-written file, so
		// each one writes its own temporary file and moves it into place.
		// Threads of one worker process share a PID, hence the random name.
		$tmp = $this->cacheFile . '.tmp-' . bin2hex(random_bytes(6));

		try {
			$this->writeRendition($tmp, $size, $mode, $enlarge, $quality);

			if (!rename($tmp, $this->cacheFile)) {
				throw new RuntimeException('Assets error: could not move rendition into place');
			}
		} catch (ImageResizeException $e) {
			throw new RuntimeException('Assets error: ' . $e->getMessage(), $e->getCode(), previous: $e);
		} finally {
			if (is_file($tmp)) {
				unlink($tmp);
			}
		}
	}

	protected function writeRendition(
		string $tmp,
		Size $size,
		ResizeMode $mode,
		bool $enlarge,
		?int $quality,
	): void {
		// Gumlet keeps only the first frame, so an animated GIF is
		// materialized as a copy of the original instead.
		if (Util::isAnimatedGif($this->file)) {
			if (!copy($this->file, $tmp)) {
				throw new RuntimeException('Assets error: could not copy animated gif');
			}

			return;
		}

		$image = match ($mode) {
			ResizeMode::Width => $this->get()->resizeToWidth($size->firstDimension, $enlarge),
			ResizeMode::Fit => $this->get()->resizeToBestFit(
				$size->firstDimension,
				$size->secondDimension,
				$enlarge,
			),
			ResizeMode::Crop => $this->get()->crop(
				$size->firstDimension,
				$size->secondDimension ?? $size->firstDimension,
				$enlarge,
				$size->cropMode ?? ImageResize::CROPCENTER,
			),
			ResizeMode::Height => $this->get()->resizeToHeight($size->firstDimension, $enlarge),
			ResizeMode::LongSide => $this->get()->resizeToLongSide($size->firstDimension, $enlarge),
			ResizeMode::ShortSide => $this->get()->resizeToShortSide($size->firstDimension, $enlarge),
			ResizeMode::Resize => $this->get()->resize(
				$size->firstDimension,
				$size->secondDimension,
				$enlarge,
			),
		};

		$image->save($tmp, quality: $quality);
	}

	protected function getCacheFilePath(string $name): string
	{
		$info = pathinfo($this->relativeFile);
		$relativeDir = $info['dirname'] ?? null;
		// pathinfo does not handle multiple dots like .tar.gz well
		$filenameSegments = explode('.', $info['basename']);
		$filenameExtension = array_pop($filenameSegments);
		$filenameBasename = implode('.', $filenameSegments);

		$cacheDir = $this->assets->cacheDir;

		if ($relativeDir !== '/') {
			$cacheDir .= $relativeDir;

			Path::ensureDirectory($cacheDir);
		}

		return $cacheDir . '/' . $filenameBasename . '-' . $name . '.' . $filenameExtension;
	}
}
