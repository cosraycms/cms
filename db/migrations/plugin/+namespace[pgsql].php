<?php

declare(strict_types=1);

namespace Quma\Migrations\M261116_PluginNamespace;

use Celema\Quma\Contract;
use Celema\Quma\Environment;
use Cosray\Plugin\Namespaces;

/**
 * Creates the schemas of the registered plugins, as the install migration
 * creates Cosray's. It is part of each plugin's migration namespace, where
 * its name sorts before the plugin's own files, so it runs once for every
 * plugin a site adds. A schema that exists is left alone: an administrator
 * may have created it for a role that may not.
 */
final class Migration implements Contract\Migration
{
	public function __construct(
		private Namespaces $namespaces,
	) {}

	public function run(Environment $env): void
	{
		foreach ($this->namespaces->schemas() as $schema) {
			$exists = $env->db->execute(
				'SELECT 1 AS found FROM pg_namespace WHERE nspname = :schema',
				['schema' => $schema],
			)->first();

			if ($exists === null) {
				$env->db->execute("CREATE SCHEMA {$schema};")->run();
			}
		}
	}
}

return Migration::class;
