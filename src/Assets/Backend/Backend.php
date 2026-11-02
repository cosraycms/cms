<?php

declare(strict_types=1);

namespace Cosray\Assets\Backend;

use Cosray\Assets\Recipe;
use Cosray\Exception\RuntimeException;

/**
 * Turns an image into a rendition. It decodes, shows the image upright, cuts,
 * scales and encodes; the recipe has made every decision.
 *
 * @internal
 */
interface Backend
{
	/**
	 * @param string $source A readable image file
	 * @param string $target The file to write, in the recipe's format or else that of the source
	 * @throws RuntimeException When the image cannot be read, written or afforded
	 */
	public function render(string $source, string $target, Recipe $recipe): void;
}
