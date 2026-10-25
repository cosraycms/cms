<?php

declare(strict_types=1);

namespace Cosray\Exception;

use Celema\Core\Exception\HttpConflict;

/** A delete refused because the node, or a descendant it would take along, is locked. */
final class NodeLocked extends HttpConflict implements CmsException {}
