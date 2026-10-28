<?php

declare(strict_types=1);

namespace Cosray\Testing;

use Celema\Quma\Connection;
use Celema\Quma\Database as QumaDatabase;
use Closure;
use Cosray\Bootstrap as CmsBootstrap;
use Override;

/**
 * The bootstrap of an app that a test case builds: the app's database joins
 * the test's transaction, and requests run no jobs after their response.
 *
 * @internal
 */
final class Bootstrap extends CmsBootstrap
{
	private static bool $active = false;

	/** Whether apps created now are built for a test. */
	public static function active(): bool
	{
		return self::$active;
	}

	/**
	 * Every app created by $build is built for a test.
	 *
	 * @template T
	 *
	 * @param Closure(): T $build
	 * @return T
	 */
	public static function within(Closure $build): mixed
	{
		$active = self::$active;
		self::$active = true;

		try {
			return $build();
		} finally {
			self::$active = $active;
		}
	}

	#[Override]
	protected function createDatabase(Connection $connection): QumaDatabase
	{
		return new Database($connection);
	}

	/** The connection outlives the request, with the test's transaction open. */
	#[Override]
	protected function releaseDatabase(): void
	{
		$this->db->reset();
	}

	/** Jobs stay queued, so a test reads what a request queued. */
	#[Override]
	protected function runJobs(): void {}
}
