<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/** Editors can name who made each of the field's images. */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Attribution {}
