<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/**
 * The panel template that draws a block type on the editor canvas instead
 * of the generic form, e.g. `#[Editor('my-plugin:button')]` for a template
 * a plugin registered with `Registrar::templates()`. Only block types use it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Editor
{
	public function __construct(
		public string $template,
	) {}
}
