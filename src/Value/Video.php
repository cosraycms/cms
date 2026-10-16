<?php

declare(strict_types=1);

namespace Cosray\Value;

use Override;

class Video extends File
{
	#[Override]
	public function __toString(): string
	{
		$url = $this->url();
		$mimetype = $this->mimeType();

		return "<video controls><source src=\"{$url}\" type=\"{$mimetype}\"/></video>";
	}
}
