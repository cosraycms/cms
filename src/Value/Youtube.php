<?php

declare(strict_types=1);

namespace Celemas\Cms\Value;

class Youtube extends Value
{
	public function __toString(): string
	{
		$x = (float) $this->data['aspectRatioX'];
		$y = (float) $this->data['aspectRatioY'];
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
			. $this->data['value']
			. '" allowfullscreen></iframe>'
			. '</div></div>'
		);
	}

	public function unwrap(): mixed
	{
		return $this->data['id'] ?? null;
	}

	public function json(): mixed
	{
		return $this->unwrap();
	}

	public function isset(): bool
	{
		return isset($this->data['id']) ? true : false;
	}
}
