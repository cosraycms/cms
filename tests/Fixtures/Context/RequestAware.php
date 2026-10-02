<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Context;

use Celema\Container\Container;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Quma\Database;
use Cosray\Config;
use Cosray\Context;

/** Asks for everything Context::create() provides for a request. */
final class RequestAware
{
	public function __construct(
		public readonly Context $context,
		public readonly Request $request,
		public readonly Config $config,
		public readonly Database $db,
		public readonly Factory $factory,
		public readonly Container $container,
		public readonly RequestService $service,
	) {}
}
