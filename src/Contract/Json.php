<?php

declare(strict_types=1);

namespace Cosray\Contract;

use Cosray\Api\Serializer;

/**
 * A node answering the JSON request of its own URL (`Accept:
 * application/json`) with data of its own instead of the API's resolved
 * representation. The serializer gives that representation for this or any
 * other node, so composed payloads stay free of panel and user data.
 */
interface Json
{
	public function json(Serializer $serializer): mixed;
}
