<?php

declare(strict_types=1);

namespace Cosray\Value;

class Youtube extends Value
{
	public function __toString(): string
	{
		$x = (float) ($this->meta('aspectRatioX', 16) ?: 16);
		$y = (float) ($this->meta('aspectRatioY', 9) ?: 9);
		$percent = number_format(($y / $x) * 100, 2, '.', '');
		$iframeStyle = 'position: absolute; top: 0; left: 0; width: 100%; height: 100%';

		return (
			'<div class="youtube-container">'
			. '<div style="position: relative; padding-top: '
			. $percent
			. '%">'
			. '<iframe class="youtube" style="'
			. $iframeStyle
			. '" '
			. 'src="https://www.youtube.com/embed/'
			. $this->unwrap()
			. '" allowfullscreen></iframe>'
			. '</div></div>'
		);
	}

	public function unwrap(): mixed
	{
		return $this->value();
	}

	public function json(): mixed
	{
		return $this->unwrap();
	}

	public function isset(): bool
	{
		return is_string($this->unwrap()) && $this->unwrap() !== '';
	}
}
