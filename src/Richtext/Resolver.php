<?php

declare(strict_types=1);

namespace Cosray\Richtext;

use Cosray\Assets\Asset;

/**
 * Resolves the uid references a document carries while rendering:
 * catalog assets (`image.uid`, `link.asset`), node URL paths
 * (`link.node`), and locale maps such as catalog asset meta.
 */
interface Resolver
{
	public function asset(string $uid): ?Asset;

	/** The referenced node's URL path for the current locale. */
	public function nodePath(string $uid): ?string;

	/** Resolve a locale map along the current locale fallback chain. */
	public function localize(array $map): mixed;
}
