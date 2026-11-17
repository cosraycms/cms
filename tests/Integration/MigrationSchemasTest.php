<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Connection;
use Celema\Quma\Environment;
use Cosray\Migration\Schemas;
use Cosray\Plugin\Namespaces;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class MigrationSchemasTest extends TestCase
{
	public function testCosraysSchemaIsCreatedBeforeItsInstallMigrations(): void
	{
		$this->assertSame(['cms_schema_check'], $this->create('install', ['cms.prefix' => 'cms_schema_check.']));
	}

	public function testAPluginsSchemaIsCreatedBeforeItsMigrations(): void
	{
		$this->assertSame(['test_plugin'], $this->create('test-plugin'));
		$this->assertSame(['shop_check'], $this->create('test-plugin', ['test-plugin.prefix' => 'shop_check.']));
	}

	public function testAnExistingSchemaIsLeftAlone(): void
	{
		// Cosray's schema exists in the test database; creating it again would fail.
		$this->assertSame([], $this->create('install'));
	}

	public function testNamePrefixesAndOtherNamespacesNeedNoSchema(): void
	{
		$this->assertSame([], $this->create('test-plugin', ['test-plugin.prefix' => 'test_plugin_']));
		$this->assertSame([], $this->create('install', ['cms.prefix' => 'cms_']));
		$this->assertSame([], $this->create('default'));
		$this->assertSame([], $this->create('unknown-plugin'));
	}

	public function testOtherDriversHaveNoSchemas(): void
	{
		$this->expectNotToPerformAssertions();
		// Looking up pg_namespace would fail on SQLite.
		$sqlite = new Environment(new Connection('sqlite::memory:', self::root() . '/db/sql'));

		$this->schemas([])->create($sqlite, 'install');
		$this->schemas([])->create($sqlite, 'test-plugin');
	}

	/**
	 * The schemas the hook created, in a transaction that is rolled back.
	 *
	 * @param array<string, string> $placeholders
	 * @return list<string>
	 */
	private function create(string $namespace, array $placeholders = []): array
	{
		$env = new Environment(new Connection(self::databaseDsn(), self::root() . '/db/sql'));
		$before = $this->schemaNames($env);
		$env->db->begin();

		try {
			$this->schemas($placeholders)->create($env, $namespace);

			return array_values(array_diff($this->schemaNames($env), $before));
		} finally {
			$env->db->rollback();
		}
	}

	/** @param array<string, string> $placeholders */
	private function schemas(array $placeholders): Schemas
	{
		$settings = ['db.dsn' => self::databaseDsn()];

		if ($placeholders !== []) {
			$settings['db.placeholders'] = ['pgsql' => $placeholders];
		}

		$db = $this->config($settings)->db;

		return new Schemas($db, new Namespaces($db, ['test-plugin']));
	}

	/** @return list<string> */
	private function schemaNames(Environment $env): array
	{
		/** @var list<string> */
		return array_column($env->db->execute('SELECT nspname FROM pg_namespace')->all(), 'nspname');
	}
}
