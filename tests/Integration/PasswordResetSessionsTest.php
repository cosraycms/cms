<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Database;
use Cosray\Queue;
use Cosray\Tests\IntegrationTestCase;
use Cosray\User;
use Cosray\User\PasswordResets;
use Cosray\Users;
use Throwable;

/**
 * A second process works on the same links while the first holds them in
 * an open transaction, so the user is committed and deleted afterwards.
 *
 * @internal
 *
 * @coversNothing
 */
final class PasswordResetSessionsTest extends IntegrationTestCase
{
	protected bool $useTransactions = false;
	private string $name;
	private int $id;
	private User $user;
	private PasswordResets $resets;

	protected function setUp(): void
	{
		parent::setUp();

		$this->name = uniqid('reset-concurrent-');
		$this->id = $this->createTestUser(['uid' => $this->name, 'username' => $this->name]);
		$users = new Users($this->db());
		$this->user = $users->byId($this->id) ?? $this->fail('The test user is missing.');
		$this->resets = new PasswordResets($this->db(), $users, $this->config(), new Queue($this->db()));
	}

	protected function tearDown(): void
	{
		try {
			if ($this->db()->getConn()->inTransaction()) {
				$this->db()->rollback();
			}

			$this->db()->execute('DELETE FROM cms.users_history WHERE usr = :usr', ['usr' => $this->id])->run();
			$this->db()->execute('DELETE FROM cms.users WHERE usr = :usr', ['usr' => $this->id])->run();
		} finally {
			parent::tearDown();
		}
	}

	public function testConcurrentRequestsIssueOneLink(): void
	{
		$this->db()->begin();
		$first = $this->resets->issue($this->user);
		$this->assertNotNull($first);

		$this->assertFalse($this->concurrently('issue', (string) $this->id));
		$this->assertNotNull($this->resets->user($first), 'The first link must keep working.');
	}

	public function testALinkWorksOnceForConcurrentRequests(): void
	{
		$token = $this->resets->issue($this->user);
		$this->assertNotNull($token);

		$this->db()->begin();
		$this->assertNotNull($this->resets->complete($token, 'the first new password'));

		$this->assertFalse($this->concurrently('complete', $token));
	}

	/**
	 * Runs the action in a second process, commits the open transaction
	 * once the process waits for it, and returns the action's result.
	 */
	private function concurrently(string $action, string $argument): bool
	{
		$process = proc_open(
			[PHP_BINARY, self::root() . '/tests/Fixtures/password-reset.php', $this->name, $action, $argument],
			[0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
			$pipes,
			env_vars: [...getenv(), 'COSRAY_RESET_DSN' => self::testDbDsn()],
		);
		$this->assertIsResource($process);
		fclose($pipes[0]);

		try {
			$this->awaitLockWait();
			$this->db()->commit();
			$output = stream_get_contents($pipes[1]);
			$error = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$exit = proc_close($process);
		} catch (Throwable $e) {
			proc_terminate($process);
			proc_close($process);

			throw $e;
		}

		$this->assertSame(0, $exit, (string) $error);

		return json_decode((string) $output, flags: JSON_THROW_ON_ERROR);
	}

	private function awaitLockWait(): void
	{
		$observer = new Database($this->conn());
		$sql = (string) file_get_contents(self::root() . '/tests/Fixtures/sql/fulltext/waiting.sql');
		$deadline = microtime(true) + 10;

		while (microtime(true) < $deadline) {
			if ($observer->execute($sql, ['name' => $this->name])->one()['waiting']) {
				return;
			}

			usleep(10000);
		}

		$this->fail('The second process must wait for the first transaction.');
	}
}
