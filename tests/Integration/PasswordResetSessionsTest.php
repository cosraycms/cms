<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Database;
use Cosray\Queue;
use Cosray\Tests\IntegrationTestCase;
use Cosray\User\PasswordResets;
use Cosray\Users;

/**
 * A second process works on the same links, so the user is committed and
 * deleted afterwards.
 *
 * @internal
 *
 * @coversNothing
 */
final class PasswordResetSessionsTest extends IntegrationTestCase
{
	protected bool $useTransactions = false;

	public function testConcurrentRequestsIssueOneLink(): void
	{
		$name = uniqid('reset-concurrent-');
		$id = $this->createTestUser(['uid' => $name, 'username' => $name]);
		$users = new Users($this->db());
		$user = $users->byId($id);
		$this->assertNotNull($user);
		$resets = new PasswordResets($this->db(), $users, $this->config(), new Queue($this->db()));
		$process = null;

		try {
			$this->db()->begin();
			$first = $resets->issue($user);
			$this->assertNotNull($first);

			$process = proc_open(
				[PHP_BINARY, self::root() . '/tests/Fixtures/password-reset-issue.php', (string) $id, $name],
				[0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
				$pipes,
				env_vars: [...getenv(), 'COSRAY_RESET_DSN' => self::testDbDsn()],
			);
			$this->assertIsResource($process);
			fclose($pipes[0]);
			$this->awaitLockWait($name);
			$this->db()->commit();

			$output = stream_get_contents($pipes[1]);
			$error = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$this->assertSame(0, proc_close($process), (string) $error);
			$process = null;

			$this->assertSame(['issued' => false], json_decode((string) $output, true));
			$this->assertNotNull($resets->user($first), 'The first link must keep working.');
		} finally {
			if ($this->db()->getConn()->inTransaction()) {
				$this->db()->rollback();
			}

			if (is_resource($process)) {
				proc_terminate($process);
				proc_close($process);
			}

			$this->db()->execute('DELETE FROM cms.users WHERE usr = :usr', ['usr' => $id])->run();
		}
	}

	private function awaitLockWait(string $name): void
	{
		$observer = new Database($this->conn());
		$sql = (string) file_get_contents(self::root() . '/tests/Fixtures/sql/fulltext/waiting.sql');
		$deadline = microtime(true) + 10;

		while (microtime(true) < $deadline) {
			if ($observer->execute($sql, ['name' => $name])->one()['waiting']) {
				return;
			}

			usleep(10000);
		}

		$this->fail('The second request must wait for the first link before writing its own.');
	}
}
