<?php

declare(strict_types=1);

namespace Cosray\Schema;

/**
 * How a blocks grid behaves below the reference stylesheet's container
 * threshold: `Stack` collapses every block to full width, `Preserve`
 * keeps the grid, `Custom` marks the container for site-owned rules.
 */
enum Responsive: string
{
	case Stack = 'stack';
	case Preserve = 'preserve';
	case Custom = 'custom';
}
