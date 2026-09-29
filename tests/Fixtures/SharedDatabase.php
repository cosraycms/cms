<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures;

use Celema\Quma\Database;
use PDO;

/**
 * Database whose PDO connection outlives the test that opened it.
 *
 * Opening a PostgreSQL connection costs more than most tests take, so all
 * instances with the same DSN, user, and options share one connection per
 * process, while each keeps its own SQL folders and placeholders. Tests must
 * not leave an open transaction or session state (SET without LOCAL,
 * temporary tables, session locks) behind; a test that needs a separate
 * session opens a plain Database.
 */
final class SharedDatabase extends Database
{
	/** @var array<string, PDO> */
	private static array $connections = [];

	public function connect(): static
	{
		if ($this->pdo !== null) {
			return $this;
		}

		$config = $this->conn->config;
		$key = serialize([$config->dsn, $config->pdo->username, $config->pdo->effectiveOptions()]);

		if (!isset(self::$connections[$key])) {
			parent::connect();
			self::$connections[$key] = $this->getConn();

			return $this;
		}

		$this->pdo = self::$connections[$key];
		$this->markConnected();

		return $this;
	}
}
