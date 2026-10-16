<?php

declare(strict_types=1);

namespace Cosray\Value;

use Override;

use function Cosray\escape;

class Youtube extends Value
{
	#[Override]
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
				. escape((string) $this->raw())
				. '" allowfullscreen></iframe>'
				. '</div></div>'
		);
	}

	#[Override]
	public function raw(): mixed
	{
		return $this->value();
	}

	#[Override]
	public function json(): mixed
	{
		return $this->raw();
	}

	#[Override]
	public function isset(): bool
	{
		return is_string($this->raw()) && $this->raw() !== '';
	}
}
