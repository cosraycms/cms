<?php

declare(strict_types=1);

namespace Cosray\Value;

class Video extends File
{
	public function __toString(): string
	{
		$url = $this->url();
		$mimetype = $this->mimeType();

		return "<video controls><source src=\"{$url}\" type=\"{$mimetype}\"/></video>";
	}

	protected function mediaType(): string
	{
		return 'video';
	}
}
