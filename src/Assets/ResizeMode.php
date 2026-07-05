<?php

declare(strict_types=1);

namespace Cosray\Assets;

enum ResizeMode: string
{
	case Crop = 'crop';
	case Fit = 'fit';
	case Height = 'height';
	case LongSide = 'longside';
	case Resize = 'resize';
	case ShortSide = 'shortside';
	case Width = 'width';
}
