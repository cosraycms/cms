<?php

declare(strict_types=1);

namespace Celemas\Cms\Assets;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Util\Path;
use Celemas\Core\Request;
use Gumlet\ImageResize;
use Gumlet\ImageResizeException;

class Image
{
	public readonly string $relativeFile;
	public readonly string $file;
	protected ?string $cacheFile = null;
	protected bool $isAnimated = false;

	public function __construct(
		protected readonly Request $request,
		protected readonly Assets $assets,
		string $file,
	) {
		try {
			$this->file = Path::inside($assets->assetsDir, $file, checkIsFile: true);
		} catch (RuntimeException) {
			$this->file = Path::inside($assets->assetsDir, 'not-found.jpg', checkIsFile: true);
		}

		$this->isResizable();
		$this->relativeFile = substr($this->file, strlen($assets->assetsDir));
	}

	public function path(): string
	{
		return $this->cacheFile ?: $this->file;
	}

	public function publicPath(bool $bust = false): string
	{
		$path = implode('/', array_map('rawurlencode', explode('/', str_replace(
			'\\',
			'/',
			$this->path(),
		))));

		if ($bust) {
			$buster = hash('xxh32', (string) filemtime($this->file));
			$path .= '?v=' . $buster;
		}

		return substr($path, strlen($this->assets->publicDir));
	}

	public function url(bool $bust = false): string
	{
		return $this->request->origin() . $this->publicPath($bust);
	}

	public function isResizable(): bool
	{
		return match (mime_content_type($this->file)) {
			'image/gif' => true,
			'image/jpeg' => true,
			'image/png' => true,
			'image/webp' => true,
			default => false,
		};
	}

	public function resize(Size $size, ResizeMode $mode, bool $enlarge, ?int $quality): static
	{
		if (!$this->isResizable()) {
			return $this;
		}

		$this->cacheFile = $this->getCacheFilePath($size, $mode, $enlarge);

		if (is_file($this->cacheFile)) {
			$fileMtime = filemtime($this->file);
			$cacheMtime = filemtime($this->cacheFile);

			if ($fileMtime > $cacheMtime) {
				$this->createCacheFile($size, $mode, $enlarge, $quality);
			}
		} else {
			if (Util::isAnimatedGif($this->file)) {
				return $this;
			}

			$this->createCacheFile($size, $mode, $enlarge, $quality);
		}

		return $this;
	}

	public function delete(): bool
	{
		return unlink($this->file);
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
		try {
			$image = match ($mode) {
				ResizeMode::Width => $this->get()->resizeToWidth($size->firstDimension, $enlarge),
				ResizeMode::Fit => $this->get()->resizeToBestFit(
					$size->firstDimension,
					$size->secondDimension,
					$enlarge,
				),
				ResizeMode::Crop => $this->get()->crop(
					$size->firstDimension,
					$size->secondDimension,
					$size->cropMode,
				),
				ResizeMode::Height => $this->get()->resizeToHeight($size->firstDimension, $enlarge),
				ResizeMode::LongSide => $this->get()->resizeToLongSide($size->firstDimension, $enlarge),
				ResizeMode::ShortSide => $this->get()->resizeToShortSide($size->firstDimension, $enlarge),
				ResizeMode::FreeCrop => $this->get()->freecrop(
					$size->firstDimension,
					$size->secondDimension,
					x: $size->cropMode['x'],
					y: $size->cropMode['y'],
				),
				ResizeMode::Resize => $this->get()->resize(
					$size->firstDimension,
					$size->secondDimension,
					$enlarge,
				),
			};

			$image->save($this->cacheFile, quality: $quality);
		} catch (ImageResizeException $e) {
			throw new RuntimeException('Assets error: ' . $e->getMessage(), $e->getCode(), previous: $e);
		}
	}

	protected function getCacheFilePath(Size $size, ResizeMode $mode, bool $enlarge): string
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

			// create cache sub directory if it does not exist
			if (!is_dir($cacheDir)) {
				mkdir($cacheDir, 0o755, true);
			}
		}

		$suffix = '-' . match ($mode) {
			ResizeMode::Width => 'w' . $size->firstDimension,
			ResizeMode::Fit => $size->firstDimension . 'x' . $size->secondDimension . '-fit',
			ResizeMode::Crop => $size->firstDimension
				. 'x'
				. $size->secondDimension
				. '-crop'
				. $size->cropMode,
			ResizeMode::FreeCrop => $size->firstDimension
				. 'x'
				. $size->secondDimension
				. '-crop-x'
				. $size->cropMode['x']
				. 'y'
				. $size->cropMode['y'],
			ResizeMode::Height => 'h' . $size->firstDimension,
			ResizeMode::LongSide => 'l' . $size->firstDimension,
			ResizeMode::ShortSide => 's' . $size->firstDimension,
			ResizeMode::Resize => $size->firstDimension . 'x' . $size->secondDimension . '-resize',
			default => throw new RuntimeException('Assets error: resize mode not supported'),
		};

		if ($enlarge) {
			$suffix .= '-enl';
		}

		$cacheFile = $cacheDir . '/' . $filenameBasename . $suffix;

		// Add extension
		return $cacheFile . '.' . $filenameExtension;
	}
}
