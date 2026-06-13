<?php

declare(strict_types=1);

namespace Cosray\Schema;

enum TranslateMode: string
{
	case Symmetric = 'symmetric';
	case Asymmetric = 'asymmetric';
}
