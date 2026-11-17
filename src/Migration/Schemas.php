<?php

declare(strict_types=1);

namespace Cosray\Migration;

use Celema\Quma\Environment;
use Cosray\Config\Database;
use Cosray\Plugin\Namespaces;

/**
 * Creates the schema an owner's migrations write to before they run:
 * Cosray's before its install migrations, a plugin's before the plugin's.
 * A schema that exists is left alone, as an administrator may have created
 * it for a database role that may not.
 *
 * @internal
 */
final readonly class Schemas
{
	public function __construct(
		private Database $db,
		private Namespaces $plugins,
	) {}

	public function create(Environment $env, string $namespace): void
	{
		if ($env->driver !== 'pgsql') {
			return;
		}

		$prefix = $namespace === 'install' ? $this->db->prefix('pgsql') : $this->plugins->prefix($namespace);

		if ($prefix === null || !str_ends_with($prefix, '.')) {
			return;
		}

		$schema = substr($prefix, 0, -1);
		$exists = $env
			->db
			->execute(
				'SELECT 1 AS found FROM pg_namespace WHERE nspname = :schema',
				['schema' => $schema],
			)
			->first();

		if ($exists === null) {
			$env->db->execute("CREATE SCHEMA {$schema};")->run();
		}
	}
}
