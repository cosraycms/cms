<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Container\Container;
use Celema\Quma\Connection;
use Celema\Quma\Contract\Migration;
use Celema\Quma\Contract\MigrationFactory as QumaMigrationFactory;
use Celema\Quma\Database;
use Celema\Quma\Environment;
use Override;

final class MigrationFactory implements QumaMigrationFactory
{
	public function __construct(
		protected readonly Container $container,
	) {}

	/** @param class-string<Migration> $class */
	#[Override]
	public function create(string $class, Environment $env): Migration
	{
		$container = $this->container->scope();
		$container->add(Environment::class, $env)->value();
		$container->add(Connection::class, $env->conn)->value();
		$container->add(Database::class, $env->db)->value();

		return $container->instance($class);
	}
}
