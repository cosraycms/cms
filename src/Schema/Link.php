<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/** Editors can link each of the field's images to a page, a file or an address. */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Link {}
