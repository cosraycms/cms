<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures;

use Celema\Quma\Connection;
use Celema\Quma\Database;
use Cosray\Bootstrap;
use Cosray\Config;

final class TransactionalBootstrap extends Bootstrap
{
	public function __construct(
		Config $config,
		private readonly ?Connection $sharedConnection = null,
		private readonly ?Database $sharedDatabase = null,
	) {
		parent::__construct($config);
	}

	protected function createConnection(): Connection
	{
		return $this->sharedConnection ?? parent::createConnection();
	}

	protected function createDatabase(Connection $connection): Database
	{
		return $this->sharedDatabase ?? parent::createDatabase($connection);
	}
}
