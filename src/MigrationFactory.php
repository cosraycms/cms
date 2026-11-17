<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Container\Container;
use Celema\Core\Factory\Factory;
use Celema\Quma\Connection;
use Celema\Quma\Contract\Migration;
use Celema\Quma\Contract\MigrationFactory as QumaMigrationFactory;
use Celema\Quma\Database;
use Celema\Quma\Environment;
use Celema\Verba\Verba;
use Cosray\Node\Writer;
use Override;

/**
 * Builds PHP migrations with the services a console command gets, bound to
 * the migration's database, so that what they write joins its transaction.
 */
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
		$container->add(Context::class, self::context(...))->scoped();
		$container->add(Cms::class)->scoped();
		$container->add(Writer::class)->scoped();

		return $container->instance($class);
	}

	private static function context(
		Database $db,
		Config $config,
		Container $container,
		Factory $factory,
		Locales $locales,
	): Context {
		$context = Context::console($db, $config, $container, $factory, $locales);

		// The migration commands run outside the console scope, which would
		// otherwise have activated a translator for the process.
		if (Verba::translator() === null) {
			Verba::activate($context->translator());
		}

		return $context;
	}
}
