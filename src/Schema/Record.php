<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/**
 * Marks a node type as a record, like a form submission: a save writes
 * the node directly, without a working copy, publication or online switch.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Record {}
