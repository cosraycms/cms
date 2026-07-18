<?php

declare(strict_types=1);

namespace Quma\Migrations\M260519_151046_Ddl0;

use Cosray\Config;
use Celema\Quma\Contract;
use Celema\Quma\Environment;

final class Migration implements Contract\Migration
{
	public function __construct(
		private Config $config,
	) {}

	public function run(Environment $env): void
	{
		$env->db->execute(<<<'SQL'
			CREATE EXTENSION btree_gist;
			CREATE EXTENSION btree_gin;
			CREATE EXTENSION unaccent;
			SQL)->run();

		$schema = $this->schema($env);

		if ($schema !== null) {
			$env->db->execute("CREATE SCHEMA {$schema};")->run();
		}
	}

	private function schema(Environment $env): ?string
	{
		$prefix = $this->prefix($env);

		if (!str_ends_with($prefix, '.')) {
			return null;
		}

		$schema = substr($prefix, 0, -1);

		return $schema === '' ? null : $schema;
	}

	private function prefix(Environment $env): string
	{
		$placeholders = $this->config
			->with('db.dsn', $env->conn->config->dsn)
			->db
			->placeholders;

		return $placeholders[$env->driver]['cms.prefix'] ?? $placeholders['all']['cms.prefix'] ?? '';
	}
}

return Migration::class;
