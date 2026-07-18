<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Container\Container;
use Celema\Quma\Connection;
use Celema\Quma\Environment;
use Cosray\MigrationFactory;
use Cosray\Tests\Fixtures\ContainerMigration;
use Cosray\Tests\Fixtures\MigrationFactoryDependency;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class MigrationFactoryTest extends TestCase
{
	public function testCreatesMigrationWithScopedEnvironment(): void
	{
		$container = new Container();
		$dependency = new MigrationFactoryDependency();
		$container->add(MigrationFactoryDependency::class, $dependency)->value();

		$env = new Environment([
			'default' => new Connection('sqlite::memory:', self::root() . '/db/sql'),
		], []);

		$migration = new MigrationFactory($container)->create(ContainerMigration::class, $env);

		$this->assertInstanceOf(ContainerMigration::class, $migration);
		$this->assertSame($dependency, $migration->dependency);
		$this->assertSame($env, $migration->env);
		$this->assertSame($env->conn, $migration->conn);
		$this->assertSame($env->db, $migration->db);
	}
}
