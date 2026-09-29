<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures;

use Celema\Quma\Connection;
use Celema\Quma\Database;
use Cosray\Bootstrap;
use Cosray\Config;

/**
 * Bootstrap that joins the test's connection and transaction, or else opens
 * the application database on the connection shared by all tests.
 */
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
		return $this->sharedDatabase ?? new SharedDatabase($connection);
	}
}
