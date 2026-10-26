<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Database;
use Cosray\Tests\IntegrationTestCase;
use Cosray\Util\Transaction;
use PDOException;
use RuntimeException;

final class TransactionTest extends IntegrationTestCase
{
	public function testWorkCommitsInItsOwnTransaction(): void
	{
		$db = $this->session();

		$result = Transaction::run($db, static function () use ($db): string {
			$db->execute('INSERT INTO probe VALUES (1)')->run();

			return 'done';
		});

		$this->assertSame('done', $result);
		$this->assertFalse($db->getConn()->inTransaction());
		$this->assertSame([1], $this->values($db));
	}

	public function testFailedWorkIsRolledBackAndItsExceptionRethrown(): void
	{
		$db = $this->session();
		$failure = new RuntimeException('work failed');

		try {
			Transaction::run($db, static function () use ($db, $failure): never {
				$db->execute('INSERT INTO probe VALUES (1)')->run();

				throw $failure;
			});
			$this->fail('Expected the work to fail.');
		} catch (RuntimeException $e) {
			$this->assertSame($failure, $e);
		}

		$this->assertFalse($db->getConn()->inTransaction());
		$this->assertSame([], $this->values($db));
	}

	public function testFailedWorkInsideATransactionUndoesOnlyItself(): void
	{
		$db = $this->db();
		$db->execute('CREATE TEMPORARY TABLE probe (value int) ON COMMIT DROP')->run();
		$db->execute('INSERT INTO probe VALUES (1)')->run();

		try {
			Transaction::run($db, static function () use ($db): never {
				$db->execute('INSERT INTO probe VALUES (2)')->run();
				// Aborts the transaction, as a failing statement does.
				$db->execute('SELECT 1 / 0')->run();
			});
			$this->fail('Expected the work to fail.');
		} catch (PDOException $e) {
			$this->assertSame('22012', $e->getCode());
		}

		$this->assertTrue($db->getConn()->inTransaction());
		$this->assertSame([1], $this->values($db));
	}

	public function testLostConnectionKeepsTheWorksException(): void
	{
		$db = $this->session();

		$this->assertStringContainsString('terminating connection', $this->terminate($db));
	}

	public function testLostConnectionInsideATransactionKeepsTheWorksException(): void
	{
		$db = $this->session();
		$db->begin();

		$this->assertStringContainsString('terminating connection', $this->terminate($db));
	}

	/** A separate session, as the shared test connection is inside a transaction. */
	private function session(): Database
	{
		$db = new Database($this->conn());
		$db->execute('CREATE TEMPORARY TABLE probe (value int)')->run();

		return $db;
	}

	/**
	 * Runs work that ends its own session, so undoing it fails as well, and
	 * returns the message of the exception that reaches the caller.
	 */
	private function terminate(Database $db): string
	{
		try {
			Transaction::run($db, static function () use ($db): void {
				$db->execute('SELECT pg_terminate_backend(pg_backend_pid())')->run();
			});
		} catch (PDOException $e) {
			return $e->getMessage();
		}

		$this->fail('Expected the work to fail.');
	}

	/** @return list<int> */
	private function values(Database $db): array
	{
		return array_map(
			static fn(array $row): int => (int) $row['value'],
			$db->execute('SELECT value FROM probe ORDER BY value')->all(),
		);
	}
}
