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
 * test's transaction: its own transaction is a savepoint, and the end of a
 * request rolls back only what the request left open.
 *
 * @internal
 */
final class Database extends QumaDatabase
{
	private const string SAVEPOINT = 'cosray_testing';
	private const string REQUEST = 'cosray_testing_request';

	/** @var array<string, PDO> */
	private static array $connections = [];

	private bool $isolated = false;

	/** Whether the code under test opened its transaction. */
	private bool $nested = false;

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
	public function isolate(): void
	{
		parent::begin();
		$this->isolated = true;
		$this->nested = false;
	}

	/** Rolls back the test's transaction and all the work in it. */
	public function discard(): void
	{
		$this->isolated = false;
		$this->nested = false;

		if ($this->pdo?->inTransaction()) {
			$this->pdo->rollBack();
		}
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

	#[Override]
	public function begin(): bool
	{
		if (!$this->isolated) {
			return parent::begin();
		}

		if ($this->nested) {
			throw new PDOException('There is already an active transaction');
		}

		$this->getConn()->exec('SAVEPOINT ' . self::SAVEPOINT);
		$this->nested = true;

		return true;
	}

	#[Override]
	public function commit(): bool
	{
		if (!$this->isolated) {
			return parent::commit();
		}

		$this->end('RELEASE SAVEPOINT ' . self::SAVEPOINT);

		return true;
	}

	#[Override]
	public function rollback(): bool
	{
		if (!$this->isolated) {
			return parent::rollback();
		}

		$this->end('ROLLBACK TO SAVEPOINT ' . self::SAVEPOINT, 'RELEASE SAVEPOINT ' . self::SAVEPOINT);

		return true;
	}

	#[Override]
	public function reset(): bool
	{
		if (!$this->isolated) {
			return parent::reset();
		}

		if ($this->nested) {
			$this->rollback();
		}

		return true;
	}

	#[Override]
	public function disconnect(): void
	{
		if ($this->isolated) {
			$this->reset();

			return;
		}

		parent::disconnect();
	}

	private function end(string ...$statements): void
	{
		if (!$this->nested) {
			throw new PDOException('There is no active transaction');
		}

		$this->nested = false;
		$pdo = $this->getConn();

		foreach ($statements as $statement) {
			$pdo->exec($statement);
		}
	}
}
