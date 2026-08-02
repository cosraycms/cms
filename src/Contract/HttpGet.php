<?php

declare(strict_types=1);

namespace Cosray\Contract;

use Celema\Core\Response;

/**
 * Replaces the default page render for this node.
 *
 * Cosray dispatches to it before its own handling of GET, so the node
 * also owns content negotiation for this method. Read the request body
 * through the autowired `Celema\Core\Request`.
 */
interface HttpGet
{
	public function httpGet(): Response;
}
