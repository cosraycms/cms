<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Core\App;
use Celema\Quma\Database;
use Celema\Router\Router;
use Cosray\Bootstrap;
use Cosray\Tests\Fixtures\RecordingLogger;
use Cosray\Tests\TestCase;
use Psr\Log\LoggerInterface;

final class SlowStatementsTest extends TestCase
{
	public function testAStatementAtTheThresholdIsLoggedAsAWarning(): void
	{
		$logger = new RecordingLogger();

		$this->database(['db.slow' => 0], $logger)->execute('SELECT 1 AS slow_probe')->one();

		$this->assertCount(1, $logger->records);
		[$level, $message] = $logger->records[0];
		$this->assertSame('warning', $level);
		$this->assertMatchesRegularExpression(
			'/^Slow database statement took [0-9.E-]+ s: SELECT 1 AS slow_probe$/',
			$message,
		);
	}

	public function testAFasterStatementIsNotLogged(): void
	{
		$logger = new RecordingLogger();

		$this->database(['db.slow' => 60], $logger)->execute('SELECT 1')->one();

		$this->assertSame([], $logger->records);
	}

	/** @param array<string, mixed> $settings */
	private function database(array $settings, LoggerInterface $logger): Database
	{
		$container = $this->container();
		$container->add(LoggerInterface::class, $logger);
		$app = new App($this->factory(), new Router(), $container);
		$app->load(new Bootstrap($this->config(['db.dsn' => self::databaseDsn(), ...$settings])));

		$db = $container->get(Database::class);
		$this->assertInstanceOf(Database::class, $db);

		return $db;
	}
}
