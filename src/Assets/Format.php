<?php

declare(strict_types=1);

namespace Cosray\Assets;

/** What a size in `media.sizes` can turn an image into. */
enum Format: string
{
	case Jpeg = 'jpeg';
	case Png = 'png';
	case Webp = 'webp';
	case Avif = 'avif';

	public function extension(): string
	{
		return $this === self::Jpeg ? 'jpg' : $this->value;
	}
}
