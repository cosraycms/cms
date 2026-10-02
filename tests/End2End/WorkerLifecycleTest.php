<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Celema\Container\Container;
use Celema\Core\App;
use Celema\Core\Factory\Factory;
use Celema\Core\Response;
use Celema\Quma\Connection;
use Celema\Quma\Database;
use Celema\Verba\Verba;
use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Context;
use Cosray\Tests\End2EndTestCase;

/**
 * One app instance serving several requests, as a FrankenPHP worker does.
 * Probe routes use PATCH, which the CMS catchall does not handle.
 *
 * @internal
 *
 * @coversNothing
 */
final class WorkerLifecycleTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		// These requests run the app's own database teardown instead of
		// joining a transaction the test owns.
		$this->testDb?->rollback();
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$database = $this->testDb;
		assert($database instanceof Database, 'The harness creates the test database before the app');

		return new class($config, $database) extends Bootstrap {
			public function __construct(
				Config $config,
				private readonly Database $shared,
			) {
				parent::__construct($config);
			}

			protected function createDatabase(Connection $connection): Database
			{
				return $this->shared;
			}
		};
	}

	public function testTransactionLeftOpenIsRolledBackBeforeTheNextRequest(): void
	{
		$db = $this->databaseOf($this->app);
		$this->app->patch('/probe/begin', static function (Factory $factory) use ($db): Response {
			$db->begin();

			return Response::create($factory)->text('begun');
		});
		$this->addStateProbe($this->app);

		$this->assertSame('begun', (string) $this->makeRequest('PATCH', '/probe/begin')->getBody());
		$this->assertSame('idle', (string) $this->makeRequest('PATCH', '/probe/state')->getBody());
		$this->assertTrue($db->connected());
	}

	public function testConnectionIsClosedAfterEveryRequestWithoutReuse(): void
	{
		$this->app = $this->createApp(['db.reuse' => false]);
		$db = $this->databaseOf($this->app);
		$this->addStateProbe($this->app);

		$this->assertSame('idle', (string) $this->makeRequest('PATCH', '/probe/state')->getBody());
		$this->assertFalse($db->connected());
	}

	public function testRequestsResolveThroughTheirOwnScope(): void
	{
		$scopes = [];
		$this->app->patch('/probe/scope', static function (
			Context $context,
			Container $container,
			Factory $factory,
		) use (&$scopes): Response {
			$scopes[] = $container;

			return Response::create($factory)->text($context->container === $container ? 'same' : 'different');
		});

		$this->assertSame('same', (string) $this->makeRequest('PATCH', '/probe/scope')->getBody());
		$this->makeRequest('PATCH', '/probe/scope');

		$this->assertCount(2, $scopes);
		$this->assertNotSame($scopes[0], $scopes[1]);
		$this->assertNotSame($this->app->container(), $scopes[0]);
	}

	public function testNoTranslatorStaysActiveAfterARequest(): void
	{
		$before = Verba::translator();

		$this->assertResponseOk($this->makeRequest('GET', '/panel/login'));
		$this->makeRequest('GET', '/never-existed-' . uniqid());

		$this->assertSame($before, Verba::translator());
	}

	private function addStateProbe(App $app): void
	{
		$db = $this->databaseOf($app);
		$app->patch('/probe/state', static fn(Factory $factory): Response => Response::create($factory)->text(
			$db->getConn()->inTransaction() ? 'in transaction' : 'idle',
		));
	}

	private function databaseOf(App $app): Database
	{
		$db = $app->container()->get(Database::class);
		assert($db instanceof Database, 'The app registers its database');

		return $db;
	}
}
