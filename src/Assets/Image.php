<?php

declare(strict_types=1);

namespace Cosray\Assets;

use Closure;
use Cosray\Exception\RuntimeException;
use Cosray\Util\Path;
use Gumlet\ImageResize;
use Gumlet\ImageResizeException;
use Override;

/**
 * Materializes renditions for the cache fallback route. Everything else
 * only builds URLs (`Asset::path()`/`sizePath()`); this is the single
 * place that touches the resize pipeline.
 */
class Image
{
	/** Measured peak of decoding and resizing a large JPEG, rounded up. */
	private const int BYTES_PER_PIXEL = 5;

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
	 * Materialize the rendition carrying the given name next to its native
	 * URL.
	 */
	public function resize(
		Size $size,
		ResizeMode $mode,
		bool $enlarge,
		?int $quality,
		string $name,
		?Transform $transform = null,
	): static {
		if (!$this->isResizable()) {
			return $this;
		}

		$cacheFile = $this->getCacheFilePath($name);
		$this->cacheFile = $cacheFile;

		if (!is_file($cacheFile) || filemtime($this->file) > filemtime($cacheFile)) {
			$this->createCacheFile($cacheFile, $size, $mode, $enlarge, $quality, $transform);
		}

		return $this;
	}

	/**
	 * @param ?Closure(int, int): array{x: int, y: int, w: int, h: int} $region The part of the
	 *     image to work on, read off the source once rotated. It stays one image, as a
	 *     copy of the part would take as much memory again.
	 */
	public function get(?Closure $region = null): ImageResize
	{
		return new class($this->file, $this->maxPixels(), $region) extends ImageResize {
			private int $offsetX = 0;
			private int $offsetY = 0;

			public function __construct(string $file, int $maxPixels, ?Closure $region)
			{
				$this->max_source_pixels = $maxPixels;

				parent::__construct($file);

				if ($region !== null) {
					$part = $region($this->getSourceWidth(), $this->getSourceHeight());
					$this->offsetX = $part['x'];
					$this->offsetY = $part['y'];
					$this->original_w = $part['w'];
					$this->original_h = $part['h'];
					$this->resize($part['w'], $part['h']);
				}
			}

			#[Override]
			public function save(
				?string $filename,
				?int $image_type = null,
				?int $quality = null,
				?int $permissions = null,
				array|false $exact_size = false,
			): static {
				$this->source_x += $this->offsetX;
				$this->source_y += $this->offsetY;

				return parent::save($filename, $image_type, $quality, $permissions, $exact_size);
			}
		};
	}

	/**
	 * The library's fixed default rejects ordinary camera photos. The real
	 * bound is memory: above it the request must fail, not die in a fatal error.
	 */
	private function maxPixels(): int
	{
		$limit = ini_parse_quantity((string) ini_get('memory_limit'));

		if ($limit <= 0) {
			return 0;
		}

		return max(1, (int) (($limit - memory_get_usage(true)) / self::BYTES_PER_PIXEL));
	}

	protected function createCacheFile(
		string $cacheFile,
		Size $size,
		ResizeMode $mode,
		bool $enlarge,
		?int $quality,
		?Transform $transform,
	): void {
		// Concurrent first requests must never see a half-written file, so
		// each one writes its own temporary file and moves it into place.
		// Threads of one worker process share a PID, hence the random name.
		$tmp = $cacheFile . '.tmp-' . bin2hex(random_bytes(6));

		try {
			$this->writeRendition($tmp, $size, $mode, $enlarge, $quality, $transform);

			if (!rename($tmp, $cacheFile)) {
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
		?Transform $transform,
	): void {
		// Gumlet keeps only the first frame, so an animated GIF is
		// materialized as a copy of the original instead.
		if (Util::isAnimatedGif($this->file)) {
			if (!copy($this->file, $tmp)) {
				throw new RuntimeException('Assets error: could not copy animated gif');
			}

			return;
		}

		$second = $size->secondDimension ?? $size->firstDimension;
		// A focal point decides the cut, which leaves only the scaling to the mode.
		$ratio = $mode === ResizeMode::Crop && $transform?->focal !== null ? $size->firstDimension / $second : null;
		$source = $this->get(
			$transform === null
				? null
				: static fn(int $width, int $height) => $transform->region($width, $height, $ratio),
		);

		$image = match ($mode) {
			ResizeMode::Original => $source,
			ResizeMode::Width => $source->resizeToWidth($size->firstDimension, $enlarge),
			ResizeMode::Fit => $source->resizeToBestFit(
				$size->firstDimension,
				$this->secondDimension($size, $mode),
				$enlarge,
			),
			ResizeMode::Crop => $ratio !== null
				? $source->resize($size->firstDimension, $second, $enlarge)
				: $source->crop($size->firstDimension, $second, $enlarge, $size->cropMode ?? ImageResize::CROPCENTER),
			ResizeMode::Height => $source->resizeToHeight($size->firstDimension, $enlarge),
			ResizeMode::LongSide => $source->resizeToLongSide($size->firstDimension, $enlarge),
			ResizeMode::ShortSide => $source->resizeToShortSide($size->firstDimension, $enlarge),
			ResizeMode::Resize => $source->resize(
				$size->firstDimension,
				$this->secondDimension($size, $mode),
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

	private function secondDimension(Size $size, ResizeMode $mode): int
	{
		return (
			$size->secondDimension ?? throw new RuntimeException(
				"Assets error: resize mode {$mode->name} needs two dimensions",
			)
		);
	}
}
