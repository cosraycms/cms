<?php

declare(strict_types=1);

namespace Cosray\Queue;

use Celema\Quma\Connection;
use Closure;
use PDO;
use Pdo\Pgsql;

/**
 * Waits for jobs to be pushed. Pushing a job notifies the `cosray_jobs`
 * channel once its transaction commits; the listener receives it on a
 * connection of its own, opened on first use.
 *
 * @internal
 */
final class Listener
{
	public const string CHANNEL = 'cosray_jobs';

	private ?Pgsql $pdo = null;

	/** @param Closure(): Pgsql $connect */
	public function __construct(
		private readonly Closure $connect,
	) {}

	public static function fromConnection(Connection $connection): self
	{
		$config = $connection->config;

		return new self(static fn(): Pgsql => new Pgsql(
			$config->dsn,
			$config->pdo->username,
			$config->pdo->password,
			$config->pdo->effectiveOptions($config->driver),
		));
	}

	/**
	 * Waits until a job is pushed or the time is up, and returns whether
	 * one was pushed. A signal ends the wait early.
	 */
	public function wait(float $seconds): bool
	{
		$pdo = $this->pdo ?? $this->listen();
		$this->pdo = $pdo;

		if ($pdo->getNotify(PDO::FETCH_ASSOC, max(0, (int) ceil($seconds * 1000))) === false) {
			return false;
		}

		// One wake-up serves every job pushed meanwhile.
		do {
			$more = $pdo->getNotify(PDO::FETCH_ASSOC);
		} while ($more !== false);

		return true;
	}

	private function listen(): Pgsql
	{
		$pdo = ($this->connect)();
		$pdo->exec('LISTEN ' . self::CHANNEL);

		return $pdo;
	}
}
