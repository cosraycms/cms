<?php

declare(strict_types=1);

namespace Cosray\Contract;

use Celema\Core\Response;

/**
 * Handles POST requests to this node's public path.
 *
 * Cosray dispatches to it before its own handling of POST, so the node
 * also owns content negotiation for this method. Read the request body
 * through the autowired `Celema\Core\Request`.
 */
interface HttpPost
{
	public function httpPost(): Response;
}
