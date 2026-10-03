<?php

declare(strict_types=1);

namespace Cosray\Util;

use Celema\Quma\Database;
use Throwable;

/**
 * Runs work atomically: in its own transaction, or in a savepoint when a
 * transaction is already open, so that a failure undoes only this work
 * and leaves the outer transaction usable.
 *
 * @internal
 */
final class Transaction
{
	/**
	 * Undoes the work and rethrows its exception if it throws.
	 *
	 * @template T
	 *
	 * @param callable(): T $work
	 * @return T
	 */
	public static function run(Database $db, callable $work): mixed
	{
		$nested = $db->getConn()->inTransaction();

		if ($nested) {
			$db->transaction->savepoint()->run();
		} else {
			$db->begin();
		}

		try {
			$result = $work();

			if ($nested) {
				$db->transaction->releaseSavepoint()->run();
			} else {
				$db->commit();
			}

			return $result;
		} catch (Throwable $e) {
			self::undo($db, $nested);

			throw $e;
		}
	}

	private static function undo(Database $db, bool $nested): void
	{
		try {
			if ($nested) {
				$db->transaction->rollbackSavepoint()->run();
				$db->transaction->releaseSavepoint()->run();
			} else {
				$db->rollback();
			}
		} catch (Throwable) {
			// @mago-expect lint:no-empty-catch-clause The work's exception is rethrown instead.
			// Undoing fails when the connection was lost or the commit already
			// ended the transaction. Either way the work's exception explains
			// the failure, and Quma checks the connection before reusing it.
		}
	}
}
