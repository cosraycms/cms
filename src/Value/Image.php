<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Assets;
use Cosray\Exception\RuntimeException;
use Gumlet\ImageResize;

use function Cosray\escape;

class Image extends File
{
	protected ?Assets\Size $size = null;
	protected ?Assets\ResizeMode $resizeMode = null;
	protected bool $enlarge = false;
	protected bool $lazy = true;
	protected ?int $quality = null;
	protected string $queryString = '';

	public function __toString(): string
	{
		return $this->tag(true);
	}

	public function tag(bool $bust = true, ?string $class = null): string
	{
		return sprintf(
			'<img %ssrc="%s" alt="%s" data-path-original="%s">',
			$class ? sprintf('class="%s" ', escape($class)) : '',
			$this->url($bust),
			escape($this->alt() ?: strip_tags($this->title())),
			$this->publicPath(),
		);
	}

	public function url(bool $bust = false): string
	{
		if ($this->lazy) {
			return $this->getMediaUrl($this->index);
		}

		return $this->getImage($this->index)->url($bust);
	}

	public function publicPath(bool $bust = false): string
	{
		if ($this->lazy) {
			return $this->getMediaPath($this->index);
		}

		return $this->getImage($this->index)->publicPath($bust);
	}

	public function lazy(): static
	{
		$new = clone $this;
		$new->lazy = true;

		return $new;
	}

	public function nonlazy(): static
	{
		$new = clone $this;
		$new->lazy = false;

		return $new;
	}

	public function width(int $width, bool $enlarge = false): static
	{
		$new = clone $this;
		$new->size = new Assets\Size($width);
		$new->resizeMode = Assets\ResizeMode::Width;
		$new->enlarge = $enlarge;
		$new->queryString = "?resize=width&w={$width}" . ($enlarge ? '&enlarge=true' : '');

		return $new;
	}

	public function height(int $height, bool $enlarge = false): static
	{
		$new = clone $this;
		$new->size = new Assets\Size($height);
		$new->resizeMode = Assets\ResizeMode::Height;
		$new->enlarge = $enlarge;
		$new->queryString = "?resize=height&h={$height}" . ($enlarge ? '&enlarge=true' : '');

		return $new;
	}

	public function longSide(int $size, bool $enlarge = false): static
	{
		$new = clone $this;
		$new->size = new Assets\Size($size);
		$new->resizeMode = Assets\ResizeMode::LongSide;
		$new->enlarge = $enlarge;
		$new->queryString = "?resize=longside&size={$size}" . ($enlarge ? '&enlarge=true' : '');

		return $new;
	}

	public function shortSide(int $size, bool $enlarge = false): static
	{
		$new = clone $this;
		$new->size = new Assets\Size($size);
		$new->resizeMode = Assets\ResizeMode::ShortSide;
		$new->enlarge = $enlarge;
		$new->queryString = "?resize=shortside&size={$size}" . ($enlarge ? '&enlarge=true' : '');

		return $new;
	}

	public function fit(int $width, int $height, bool $enlarge = false): static
	{
		$new = clone $this;
		$new->size = new Assets\Size($width, $height);
		$new->resizeMode = Assets\ResizeMode::Fit;
		$new->enlarge = $enlarge;
		$new->queryString = "?resize=fit&w={$width}&h={$height}" . ($enlarge ? '&enlarge=true' : '');

		return $new;
	}

	public function resize(int $width, int $height, bool $enlarge = false): static
	{
		$new = clone $this;
		$new->size = new Assets\Size($width, $height);
		$new->resizeMode = Assets\ResizeMode::Resize;
		$new->enlarge = $enlarge;
		$new->queryString = "?resize=resize&w={$width}&h={$height}" . ($enlarge ? '&enlarge=true' : '');

		return $new;
	}

	public function crop(int $width, int $height, string $position = 'center'): static
	{
		$pos = match ($position) {
			'top' => ImageResize::CROPTOP,
			'centre' => ImageResize::CROPCENTRE,
			'center' => ImageResize::CROPCENTER,
			'bottom' => ImageResize::CROPBOTTOM,
			'left' => ImageResize::CROPLEFT,
			'right' => ImageResize::CROPRIGHT,
			'topcenter' => ImageResize::CROPTOPCENTER,
			default => throw new RuntimeException('Crop position not supported: ' . $position),
		};

		$new = clone $this;
		$new->size = new Assets\Size($width, $height, $pos);
		$new->resizeMode = Assets\ResizeMode::Crop;
		$new->queryString = "?resize=crop&w={$width}&h={$height}&pos={$position}";

		return $new;
	}

	public function freecrop(
		int $width,
		int $height,
		int|false $x = false,
		int|false $y = false,
	): static {
		$new = clone $this;
		$new->size = new Assets\Size($width, $height, ['x' => $x, 'y' => $y]);
		$new->resizeMode = Assets\ResizeMode::FreeCrop;
		$new->queryString =
			"?resize=freecrop&w={$width}&h={$height}" . ($x ? "&x={$x}" : '') . ($y ? "&y={$y}" : '');

		return $new;
	}

	public function quality(int $quality): static
	{
		$new = clone $this;
		$new->quality = $quality;

		return $new;
	}

	public function link(): string
	{
		return $this->textValue('link', $this->index);
	}

	public function alt(): string
	{
		return $this->textValue('alt', $this->index);
	}

	protected function getMediaPath(int $index): string
	{
		return (
			$this->owner->config()->path->prefix
			. '/media/image/'
			. $this->assetsPath()
			. $this->getFileName($index)
			. $this->queryString
			. ($this->quality ? "&quality={$this->quality}" : '')
		);
	}

	protected function getMediaUrl(int $index): string
	{
		return $this->owner->request()->origin() . $this->getMediaPath($index);
	}

	protected function getImage(int $index): Assets\Image
	{
		$image = $this->getAssets()->image($this->assetsPath() . $this->getFileName($index));

		if ($this->size) {
			$image = $image->resize($this->size, $this->resizeMode, $this->enlarge, $this->quality);
		}

		return $image;
	}
}
