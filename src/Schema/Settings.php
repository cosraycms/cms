<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/**
 * Edits a block's sub-field in the block's settings dialog instead of
 * on the canvas. A presentation choice: storage and validation stay the
 * same. Outside a block type it has no effect.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Settings {}
