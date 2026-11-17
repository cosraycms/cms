<?php

declare(strict_types=1);

namespace Cosray\Testing;

use Celema\Quma\Database as QumaDatabase;
use Override;
use PDO;
use PDOException;

/**
 * The database of an app under test. All instances with the same DSN share
 * one connection per process, as opening one costs more than most tests
 * take. While a test isolates it, the code under test works inside the
 * test's transaction as Quma's isolate() describes, and each request runs
 * in a savepoint of its own.
 *
 * @internal
 */
final class Database extends QumaDatabase
{
	private const string REQUEST = 'cosray_testing_request';

	/** @var array<string, PDO> */
	private static array $connections = [];

	private bool $isolated = false;

	#[Override]
	public function connect(): static
	{
		if ($this->pdo !== null) {
			return $this;
		}

		$config = $this->conn->config;
		$key = serialize([$config->dsn, $config->pdo->username, $config->pdo->effectiveOptions($config->driver)]);
		$shared = self::$connections[$key] ?? null;

		if ($shared === null) {
			parent::connect();
			self::$connections[$key] = $this->getConn();

			return $this;
		}

		$this->pdo = $shared;
		$this->markConnected();

		return $this;
	}

	/** Opens the test's transaction, which holds everything until discard(). */
	#[Override]
	public function isolate(): void
	{
		parent::isolate();
		$this->isolated = true;
	}

	/** Rolls back the test's transaction and all the work in it. */
	#[Override]
	public function discard(): void
	{
		$this->isolated = false;
		parent::discard();
	}

	/**
	 * Runs a request in a savepoint. A statement that fails outside the code's
	 * own transaction aborts PostgreSQL's transaction; without a test it would
	 * fail alone, so the request's work is undone and the test can go on.
	 *
	 * @template T
	 *
	 * @param callable(): T $request
	 * @return T
	 */
	public function request(callable $request): mixed
	{
		if (!$this->isolated) {
			return $request();
		}

		$pdo = $this->getConn();
		$pdo->exec('SAVEPOINT ' . self::REQUEST);

		try {
			return $request();
		} finally {
			try {
				$pdo->exec('RELEASE SAVEPOINT ' . self::REQUEST);
			} catch (PDOException) {
				$pdo->exec('ROLLBACK TO SAVEPOINT ' . self::REQUEST);
				$pdo->exec('RELEASE SAVEPOINT ' . self::REQUEST);
			}
		}
	}
}
