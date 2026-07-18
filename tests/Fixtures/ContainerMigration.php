<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures;

use Celema\Quma\Connection;
use Celema\Quma\Contract\Migration;
use Celema\Quma\Database;
use Celema\Quma\Environment;
use Override;

final class ContainerMigration implements Migration
{
	public function __construct(
		public readonly MigrationFactoryDependency $dependency,
		public readonly Environment $env,
		public readonly Connection $conn,
		public readonly Database $db,
	) {}

	#[Override]
	public function run(Environment $env): void {}
}
