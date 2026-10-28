<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Connection;
use Cosray\Testing\Database;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @covers \Cosray\Testing\Database
 */
final class TestingDatabaseTest extends TestCase
{
	public function testUntilATestIsolatesItTheDatabaseWorksAsUsual(): void
	{
		$db = new Database(new Connection(self::databaseDsn(), self::root() . '/db/sql'));

		$this->assertSame('ran', $db->request(static fn(): string => 'ran'));
		$db->begin();
		$this->assertTrue($db->getConn()->inTransaction());
		$db->commit();
		$db->begin();
		$db->rollback();
		$this->assertFalse($db->getConn()->inTransaction());

		$db->begin();
		$this->assertTrue($db->reset());
		$this->assertFalse($db->getConn()->inTransaction());

		$db->begin();
		$db->disconnect();
		$this->assertFalse($db->connected());
		$this->assertFalse($db->getConn()->inTransaction());
	}
}
