<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Database;
use Cosray\Actor;
use Cosray\Tests\FulltextTestCase;

final class FulltextConcurrencyTest extends FulltextTestCase
{
	protected bool $useTransactions = false;

	public function db(): Database
	{
		return $this->testDb ??= new Database($this->conn());
	}

	public function testRebuildWaitsForALiveWriterAndCannotRestoreAnEarlierSnapshot(): void
	{
		$this->environment();
		$uid = uniqid('fts-concurrent-');
		$process = null;
		try {
			$this->page($uid, 'Earlier title', 'Earlier prose');
			$this->db()->begin();
			$this->store->save(
				$this->node($uid),
				$this->payload($uid, 'Latest title', 'Latest prose'),
				$this->languages,
				Actor::system(),
			);
			$process = proc_open(
				[PHP_BINARY, self::root() . '/tests/Fixtures/fulltext-rebuild.php', $uid],
				[0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
				$pipes,
				env_vars: [...getenv(), 'COSRAY_FTS_DSN' => self::testDbDsn()],
			);
			self::assertIsResource($process);
			fclose($pipes[0]);
			$observer = new Database($this->conn());
			$waitingSql = file_get_contents(self::root() . '/tests/Fixtures/sql/fulltext/waiting.sql');
			$waiting = false;
			$deadline = microtime(true) + 10;
			while (microtime(true) < $deadline) {
				$waiting = $observer->execute($waitingSql, ['name' => $uid])->one()['waiting'];
				if ($waiting) {
					break;
				}
				usleep(10000);
			}
			self::assertTrue($waiting, 'The rebuild must wait for the writer before reading its content.');
			$this->db()->commit();
			$output = stream_get_contents($pipes[1]);
			$error = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$exit = proc_close($process);
			$process = null;
			self::assertSame(0, $exit, $error);
			self::assertSame(0, json_decode($output, true, flags: JSON_THROW_ON_ERROR)['failed']);
			self::assertSame("Latest title\n\nLatest prose", $this->source($uid));
		} finally {
			if ($this->db()->getConn()->inTransaction()) {
				$this->db()->rollback();
			}
			if (is_resource($process)) {
				proc_terminate($process);
				proc_close($process);
			}
			$this->sql('cleanup', ['uid' => $uid])->run();
		}
	}
}
