<?php

declare(strict_types=1);

namespace Cosray\Testing;

use Celema\Quma\Connection;
use Celema\Quma\Database as QumaDatabase;
use Closure;
use Cosray\App;
use Cosray\Bootstrap as CmsBootstrap;
use Cosray\Config;
use Override;

/**
 * The bootstrap of an app that a test case builds: the app's database joins
 * the test's transaction, and requests run no jobs after their response.
 *
 * @internal
 */
final class Bootstrap extends CmsBootstrap
{
	/**
	 * Every app created by $create is built for a test.
	 *
	 * @template T
	 *
	 * @param Closure(): T $create
	 * @return T
	 */
	public static function within(Closure $create): mixed
	{
		return App::createdWith(static fn(Config $config): self => new self($config), $create);
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
