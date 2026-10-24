<?php

declare(strict_types=1);

namespace Cosray\Util;

use Celema\Quma\Database;
use Throwable;
use WeakMap;

/**
 * Runs work atomically: in its own transaction, or in a savepoint when a
 * transaction is already open, so that a failure undoes only this work
 * and leaves the outer transaction usable.
 *
 * @internal
 */
final class Transaction
{
	/** @var ?WeakMap<Database, list<list<callable(): void>>> pending callbacks per open run */
	private static ?WeakMap $pending = null;

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
		$pending = self::pending();
		$levels = $pending[$db] ?? [];
		$levels[] = [];
		$pending[$db] = $levels;

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
		} catch (Throwable $e) {
			self::undo($db, $nested);
			self::leave($db, false);

			throw $e;
		}

		self::leave($db, true);

		return $result;
	}

	/**
	 * Runs the callback once the work of the outermost run() around it has
	 * been done, and never when that work is undone. Outside of run() it
	 * runs right away.
	 *
	 * @param callable(): void $callback
	 */
	public static function afterCommit(Database $db, callable $callback): void
	{
		$levels = self::$pending[$db] ?? [];

		if ($levels === []) {
			$callback();

			return;
		}

		$levels[count($levels) - 1][] = $callback;
		$pending = self::pending();
		$pending[$db] = $levels;
	}

	/** @return WeakMap<Database, list<list<callable(): void>>> */
	private static function pending(): WeakMap
	{
		return self::$pending ??= new WeakMap();
	}

	/**
	 * Closes the innermost run: its callbacks move to the run around it, or
	 * run when it was the outermost; an undone run drops them.
	 */
	private static function leave(Database $db, bool $done): void
	{
		$pending = self::pending();
		$levels = $pending[$db] ?? [];
		$callbacks = array_pop($levels) ?? [];

		if ($levels !== []) {
			if ($done) {
				$levels[count($levels) - 1] = [...$levels[count($levels) - 1], ...$callbacks];
			}

			$pending[$db] = $levels;

			return;
		}

		unset($pending[$db]);

		if ($done) {
			foreach ($callbacks as $callback) {
				$callback();
			}
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
